<?php

namespace App\Services\Mcp;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ContractorMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
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
     * @return array<string, mixed>
     */
    public function get(User $user, int $contractorId): array
    {
        $this->access->requireContractorsArea($user);

        $builder = Contractor::query()->with('owner:id,name');

        $this->access->applyContractorsScope($builder, $user);

        /** @var Contractor $contractor */
        $contractor = $builder->whereKey($contractorId)->firstOrFail();

        return $this->detail($contractor);
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
}
