<?php

namespace App\Services\Mcp;

use App\Models\Contractor;
use App\Models\User;
use App\Services\Contractor\ContractorContextBuilder;
use App\Services\DaDataService;
use App\Support\ContractorIdentity;
use App\Support\MailSync\MailContractorAllowlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractorMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly DaDataService $daData,
        private readonly ContractorContextBuilder $contextBuilder,
    ) {}

    /**
     * @return array{contractors: list<array<string, mixed>>, total: int}
     */
    public function search(User $user, string $query, int $limit = 15, ?string $type = null): array
    {
        $this->access->requireContractorsArea($user);

        $needle = trim($query);
        $limit = max(1, min($limit, 25));

        $builder = Contractor::query()->orderBy('name');

        $this->access->applyContractorsScope($builder, $user);

        if (in_array($type, ['customer', 'carrier', 'contractor', 'both'], true)) {
            $builder->where('type', $type);
        }

        if ($needle !== '') {
            $builder->where(function (Builder $scoped) use ($needle): void {
                $scoped->where('name', 'like', '%'.$needle.'%');

                if (Schema::hasColumn('contractors', 'full_name')) {
                    $scoped->orWhere('full_name', 'like', '%'.$needle.'%');
                }

                if (Schema::hasColumn('contractors', 'inn')) {
                    $scoped->orWhere('inn', 'like', '%'.$needle.'%');
                }

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $scoped->orWhere('id', (int) $needle);
                }
            });
        }

        $contractors = $builder->limit($limit)->get();

        return [
            'contractors' => $contractors->map(fn (Contractor $contractor): array => $this->summarize($contractor))->all(),
            'total' => $contractors->count(),
        ];
    }

    /**
     * @param  array{
     *     type?: string,
     *     name?: string|null,
     *     inn?: string|null,
     *     kpp?: string|null,
     *     ogrn?: string|null,
     *     okpo?: string|null,
     *     legal_form?: string|null,
     *     full_name?: string|null,
     *     legal_address?: string|null,
     *     actual_address?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     contact_person?: string|null,
     *     autofill_from_inn?: bool|null
     * }  $payload
     * @return array{contractor: array<string, mixed>, show_path: string, autofill_applied: bool}
     */
    public function create(User $user, array $payload): array
    {
        $this->access->requireContractorsArea($user);

        $type = (string) ($payload['type'] ?? 'customer');
        $name = ContractorIdentity::normalizeName($payload['name'] ?? '');
        $inn = ContractorIdentity::normalizeInn($payload['inn'] ?? null);
        $autofillFromInn = ($payload['autofill_from_inn'] ?? true) !== false;
        $autofillApplied = false;

        $attributes = [
            'type' => $type,
            'name' => $name,
            'inn' => $inn,
            'kpp' => $this->nullableString($payload['kpp'] ?? null),
            'ogrn' => $this->nullableString($payload['ogrn'] ?? null),
            'okpo' => $this->nullableString($payload['okpo'] ?? null),
            'legal_form' => $this->nullableString($payload['legal_form'] ?? null),
            'full_name' => $this->nullableString($payload['full_name'] ?? null),
            'legal_address' => $this->nullableString($payload['legal_address'] ?? null),
            'actual_address' => $this->nullableString($payload['actual_address'] ?? null),
            'phone' => $this->nullableString($payload['phone'] ?? null),
            'email' => $this->nullableString($payload['email'] ?? null),
            'contact_person' => $this->nullableString($payload['contact_person'] ?? null),
        ];

        if ($attributes['name'] === '' && $inn !== null && $autofillFromInn) {
            $suggestion = $this->resolvePartySuggestionByInn($inn);

            if ($suggestion !== null) {
                $this->applyPartySuggestion($attributes, $suggestion);
                $autofillApplied = true;
            }
        }

        if ($attributes['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'Укажите название контрагента или полный ИНН для автозаполнения из DaData.',
            ]);
        }

        Validator::make($attributes, [
            'type' => ['required', Rule::in(['customer', 'carrier', 'contractor', 'both'])],
            'name' => ['required', 'string', 'max:255', Rule::unique('contractors', 'name')],
            'inn' => ['nullable', 'string', 'max:20', Rule::unique('contractors', 'inn')],
            'kpp' => ['nullable', 'string', 'max:20'],
            'ogrn' => ['nullable', 'string', 'max:20'],
            'okpo' => ['nullable', 'string', 'max:20'],
            'legal_form' => ['nullable', Rule::in(['ooo', 'zao', 'ao', 'ip', 'samozanyaty', 'other'])],
            'full_name' => ['nullable', 'string', 'max:255'],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'actual_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $createAttributes = [
            ...$attributes,
            'is_active' => true,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'is_verified' => false,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        if (Schema::hasColumn('contractors', 'work_status')) {
            $createAttributes['work_status'] = 'active';
        }

        $contractor = Contractor::query()->create($createAttributes);
        MailContractorAllowlist::forgetCache();

        return [
            'contractor' => $this->detail($contractor->fresh('owner:id,name')),
            'show_path' => route('contractors.show', $contractor),
            'autofill_applied' => $autofillApplied,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(User $user, int $contractorId): array
    {
        $this->access->requireContractorsArea($user);

        $builder = Contractor::query()->with('owner:id,name');

        $this->access->applyContractorsScope($builder, $user);

        /** @var Contractor $contractor */
        $contractor = $builder->whereKey($contractorId)->firstOrFail();

        $detail = $this->detail($contractor);

        if (Schema::hasTable('contractor_portraits')) {
            $detail['portrait_context'] = $this->contextBuilder->build($contractor);
        }

        return $detail;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Contractor $contractor): array
    {
        return [
            'id' => $contractor->id,
            'name' => $contractor->name,
            'full_name' => Schema::hasColumn('contractors', 'full_name') ? $contractor->full_name : null,
            'type' => $contractor->type,
            'inn' => $contractor->inn,
            'phone' => $contractor->phone,
            'email' => $contractor->email,
            'work_status' => $contractor->work_status,
            'is_active' => (bool) $contractor->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Contractor $contractor): array
    {
        $summary = $this->summarize($contractor);

        $summary['kpp'] = $contractor->kpp;
        $summary['ogrn'] = $contractor->ogrn;
        $summary['legal_address'] = $contractor->legal_address;
        $summary['actual_address'] = $contractor->actual_address;
        $summary['contact_person'] = $contractor->contact_person;
        $summary['signer_name_nominative'] = $contractor->signer_name_nominative;
        $summary['signer_position'] = $contractor->signer_position;
        $summary['edo_provider'] = $contractor->edo_provider;
        $summary['edo_number'] = $contractor->edo_number;
        $summary['bank_name'] = $contractor->bank_name;
        $summary['is_verified'] = (bool) $contractor->is_verified;
        $summary['owner_name'] = $contractor->owner?->name;

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePartySuggestionByInn(string $inn): ?array
    {
        if (! in_array(strlen($inn), [10, 12], true)) {
            return null;
        }

        $suggestions = $this->daData->suggestParty($inn, 1);

        return is_array($suggestions[0] ?? null) ? $suggestions[0] : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $suggestion
     */
    private function applyPartySuggestion(array &$attributes, array $suggestion): void
    {
        $party = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];

        $attributes['name'] = ContractorIdentity::normalizeName($suggestion['value'] ?? $attributes['name']);

        if (($attributes['inn'] ?? null) === null && isset($party['inn'])) {
            $attributes['inn'] = ContractorIdentity::normalizeInn($party['inn']);
        }

        if (($attributes['kpp'] ?? null) === null && isset($party['kpp'])) {
            $attributes['kpp'] = (string) $party['kpp'];
        }

        if (($attributes['ogrn'] ?? null) === null && isset($party['ogrn'])) {
            $attributes['ogrn'] = (string) $party['ogrn'];
        }

        if (($attributes['okpo'] ?? null) === null && isset($party['okpo'])) {
            $attributes['okpo'] = (string) $party['okpo'];
        }

        if (($attributes['full_name'] ?? null) === null && isset($party['name']['full_with_opf'])) {
            $attributes['full_name'] = (string) $party['name']['full_with_opf'];
        }

        $address = is_array($party['address'] ?? null) ? (string) ($party['address']['value'] ?? '') : '';

        if ($address !== '') {
            if (($attributes['legal_address'] ?? null) === null) {
                $attributes['legal_address'] = $address;
            }

            if (($attributes['actual_address'] ?? null) === null) {
                $attributes['actual_address'] = $address;
            }
        }

        if (($party['type'] ?? null) === 'INDIVIDUAL' && ($attributes['legal_form'] ?? null) === null) {
            $attributes['legal_form'] = 'ip';
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
