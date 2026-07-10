<?php

namespace App\Models;

use App\Support\EdoProviderDictionary;
use App\Support\OwnFleetCatalog;
use App\Support\RoleAccess;
use App\Support\UserDashboardDepartmentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

class Contractor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'full_name',
        'short_description',
        'inn',
        'kpp',
        'ogrn',
        'okpo',
        'legal_form',
        'legal_address',
        'actual_address',
        'postal_address',
        'phone',
        'email',
        'mail_sync_domains',
        'website',
        'contact_person',
        'contact_person_phone',
        'contact_person_email',
        'contact_person_position',
        'signer_name_nominative',
        'signer_name_prepositional',
        'signer_position',
        'signer_authority_basis',
        'edo_provider',
        'edo_number',
        'bank_name',
        'bik',
        'account_number',
        'correspondent_account',
        'bank_accounts',
        'ati_profiles',
        'ati_id',
        'transport_requirements',
        'specializations',
        'activity_types',
        'rating',
        'completed_orders',
        'metadata',
        'debt_limit',
        'debt_limit_currency',
        'stop_on_limit',
        'default_customer_payment_form',
        'default_customer_payment_term',
        'default_customer_payment_schedule',
        'default_carrier_payment_form',
        'default_carrier_payment_term',
        'default_carrier_payment_schedule',
        'default_customer_norms_penalties',
        'default_carrier_norms_penalties',
        'cooperation_terms_notes',
        'is_active',
        'work_status',
        'work_pause_is_automatic',
        'is_verified',
        'verified_at',
        'is_own_company',
        'is_non_resident',
        'has_english_requisites',
        'name_en',
        'full_name_en',
        'legal_address_en',
        'actual_address_en',
        'postal_address_en',
        'contact_person_en',
        'bank_name_en',
        'signer_name_nominative_en',
        'signer_name_prepositional_en',
        'signer_position_en',
        'signer_authority_basis_en',
        'non_resident_corr_bank_name',
        'non_resident_corr_bank_swift',
        'non_resident_corr_settlement_account',
        'non_resident_corr_bank_account',
        'cnaps_code',
        'owner_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ati_profiles' => 'array',
            'transport_requirements' => 'array',
            'specializations' => 'array',
            'activity_types' => 'array',
            'metadata' => 'array',
            'bank_accounts' => 'json:unicode',
            'mail_sync_domains' => 'array',
            'debt_limit' => 'decimal:2',
            'default_customer_payment_schedule' => 'json:unicode',
            'default_carrier_payment_schedule' => 'json:unicode',
            'default_customer_norms_penalties' => 'json:unicode',
            'default_carrier_norms_penalties' => 'json:unicode',
            'is_active' => 'boolean',
            'work_pause_is_automatic' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'is_own_company' => 'boolean',
            'is_non_resident' => 'boolean',
            'has_english_requisites' => 'boolean',
            'stop_on_limit' => 'boolean',
            'rating' => 'decimal:2',
        ];
    }

    public function isOwnCompanyProfile(): bool
    {
        if (OwnFleetCatalog::isVirtualFleetContractor($this)) {
            return false;
        }

        return (bool) ($this->is_own_company ?? false);
    }

    public function isVirtualOwnFleetContractor(): bool
    {
        return OwnFleetCatalog::isVirtualFleetContractor($this);
    }

    /**
     * Контрагенты с признаком «Своя компания» в справочнике.
     *
     * @param  Builder<Contractor>  $query
     * @return Builder<Contractor>
     */
    public function scopeOwnCompanyProfiles(Builder $query): Builder
    {
        if (! Schema::hasColumn($this->getTable(), 'is_own_company')) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('is_own_company', true)
            ->where('name', '!=', OwnFleetCatalog::CONTRACTOR_NAME);
    }

    /**
     * @return HasOne<ContractorPortrait, $this>
     */
    public function portrait(): HasOne
    {
        return $this->hasOne(ContractorPortrait::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leadsAsCounterparty(): HasMany
    {
        return $this->hasMany(Lead::class, 'counterparty_id');
    }

    /**
     * @return HasMany<ContractorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ContractorContact::class)->orderByDesc('is_primary')->orderBy('full_name');
    }

    /**
     * @return HasMany<ContractorInteraction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ContractorInteraction::class)->latest('contacted_at');
    }

    /**
     * @return HasMany<ContractorInsightDraft, $this>
     */
    public function insightDrafts(): HasMany
    {
        return $this->hasMany(ContractorInsightDraft::class);
    }

    /**
     * @return HasMany<ContractorDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ContractorDocument::class)->latest('document_date');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function customerOrders(): HasMany
    {
        $relation = $this->hasMany(Order::class, 'customer_id');

        if (! Schema::hasColumn($relation->getRelated()->getTable(), 'deleted_at')) {
            return $relation->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $relation;
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function carrierOrders(): HasMany
    {
        $relation = $this->hasMany(Order::class, 'carrier_id');

        if (! Schema::hasColumn($relation->getRelated()->getTable(), 'deleted_at')) {
            return $relation->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $relation;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Scope a query to apply visibility rules based on user role.
     *
     * @param  Builder  $query
     * @param  string|null  $typeFilter  Optional type filter ('customer', 'carrier', 'contractor', 'both')
     * @param  list<int>  $alwaysIncludeIds  IDs that must remain visible (например, уже выбранные в заказе)
     * @return Builder
     */
    public function scopeVisibleTo($query, ?User $user = null, ?string $typeFilter = null, array $alwaysIncludeIds = [])
    {
        if (! $user) {
            return $query;
        }

        $alwaysIncludeIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $alwaysIncludeIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($user->isAdmin() || RoleAccess::userHasPermission($user, 'view_all_contractors')) {
            if ($typeFilter === 'carrier') {
                $this->applyCarrierLikeFilter($query);
            } elseif (in_array($typeFilter, ['customer', 'contractor', 'both'], true)) {
                $query->where('type', $typeFilter);
            }

            return $query;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'contractors');

        if ($scope === 'all') {
            if ($typeFilter === 'carrier') {
                $this->applyCarrierLikeFilter($query);
            } elseif (in_array($typeFilter, ['customer', 'contractor', 'both'], true)) {
                $query->where('type', $typeFilter);
            }

            return $query;
        }

        $ownerUserIds = $scope === 'department'
            ? UserDashboardDepartmentScope::departmentUserIds($user)
            : [(int) $user->id];

        if ($ownerUserIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($outer) use ($ownerUserIds, $typeFilter, $alwaysIncludeIds): void {
            if ($alwaysIncludeIds !== []) {
                $outer->whereIn('id', $alwaysIncludeIds);
            }

            $outer->orWhere(function ($visibility) use ($ownerUserIds, $typeFilter): void {
                if ($typeFilter === 'customer') {
                    $visibility->where(function ($inner) use ($ownerUserIds): void {
                        $inner->whereIn('type', ['customer', 'both'])
                            ->whereIn('owner_id', $ownerUserIds);
                    });

                    return;
                }

                if ($typeFilter === 'carrier') {
                    $this->applyCarrierLikeFilter($visibility);

                    return;
                }

                if ($typeFilter === 'contractor') {
                    $visibility->where('type', 'contractor');

                    return;
                }

                if ($typeFilter === 'both') {
                    $visibility->where('type', 'both');

                    return;
                }

                $visibility->whereIn('type', ['carrier', 'both', 'contractor'])
                    ->orWhere(function ($subQ) use ($ownerUserIds): void {
                        $subQ->whereIn('type', ['customer', 'both'])
                            ->whereIn('owner_id', $ownerUserIds);
                    });
            });
        });
    }

    /**
     * Контрагенты, которых можно выбирать как перевозчика/владельца ТС: перевозчики,
     * универсальные контрагенты и собственные компании.
     *
     * @param  Builder  $query
     */
    private function applyCarrierLikeFilter($query): void
    {
        $query->where(function ($carrierLike): void {
            $carrierLike->whereIn('type', ['carrier', 'both']);

            if (Schema::hasColumn('contractors', 'is_own_company')) {
                $carrierLike->orWhere('is_own_company', true);
            }
        });
    }

    /**
     * @return array{edo_provider: ?string, edo_number: ?string}
     */
    public function edoPrintPayload(): array
    {
        return [
            'edo_provider' => EdoProviderDictionary::label($this->edo_provider),
            'edo_number' => blank($this->edo_number) ? null : trim((string) $this->edo_number),
        ];
    }

    /**
     * Нужны для печати, если плоские поля contractors.bank_name / bik / счета пусты.
     *
     * @return array{bank_name: ?string, bik: ?string, account_number: ?string, correspondent_account: ?string}
     */
    public function bankDetailsFromAccountsFallback(): array
    {
        return $this->extractBankRowDetails($this->resolvePrimaryBankAccountRow());
    }

    /**
     * Реквизиты счёта из bank_accounts по id строки (как в карточке контрагента).
     * Если id не найден — то же поведение, что и {@see bankDetailsFromAccountsFallback()}.
     *
     * @return array{bank_name: ?string, bik: ?string, account_number: ?string, correspondent_account: ?string}
     */
    public function bankDetailsForAccountId(?string $accountId): array
    {
        if ($accountId === null || $accountId === '') {
            return $this->bankDetailsFromAccountsFallback();
        }

        $accounts = $this->bank_accounts;
        if (! is_array($accounts) || $accounts === []) {
            return $this->bankDetailsFromAccountsFallback();
        }

        foreach ($accounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rowId = $row['id'] ?? null;
            if ($rowId === null || $rowId === '') {
                continue;
            }
            if ((string) $rowId === (string) $accountId) {
                return $this->extractBankRowDetails($row);
            }
        }

        return $this->bankDetailsFromAccountsFallback();
    }

    /**
     * @return array{bank_name: ?string, bik: ?string, account_number: ?string, correspondent_account: ?string}
     */
    private function extractBankRowDetails(?array $row): array
    {
        $empty = [
            'bank_name' => null,
            'bik' => null,
            'account_number' => null,
            'correspondent_account' => null,
        ];

        if (! is_array($row)) {
            return $empty;
        }

        $pick = static function (mixed $v): ?string {
            if (! is_string($v)) {
                return null;
            }
            $t = trim($v);

            return $t === '' ? null : $t;
        };

        return [
            'bank_name' => $pick($row['bank_name'] ?? null),
            'bik' => $pick($row['bik'] ?? null),
            'account_number' => $pick($row['account_number'] ?? null),
            'correspondent_account' => $pick($row['correspondent_account'] ?? null),
        ];
    }

    /**
     * Строка основного (или первого) счёта в bank_accounts.
     */
    private function resolvePrimaryBankAccountRow(): ?array
    {
        $accounts = $this->bank_accounts;
        if (! is_array($accounts) || $accounts === []) {
            return null;
        }

        $primary = collect($accounts)->first(
            fn (mixed $row): bool => is_array($row) && filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        if (! is_array($primary)) {
            $first = $accounts[0] ?? null;

            return is_array($first) ? $first : null;
        }

        return $primary;
    }

    /**
     * Нерезидентские реквизиты банка-корреспондента и CNAPS для печатных форм (ключи в снимке customer / carrier / …).
     *
     * @return array{
     *     is_non_resident: string,
     *     non_resident_corr_bank_name: ?string,
     *     non_resident_corr_bank_swift: ?string,
     *     non_resident_corr_settlement_account: ?string,
     *     non_resident_corr_bank_account: ?string,
     *     cnaps_code: ?string
     * }
     */
    public function nonResidentPrintPayload(): array
    {
        return [
            'is_non_resident' => $this->is_non_resident ? 'Да' : 'Нет',
            'non_resident_corr_bank_name' => blank($this->non_resident_corr_bank_name) ? null : trim((string) $this->non_resident_corr_bank_name),
            'non_resident_corr_bank_swift' => blank($this->non_resident_corr_bank_swift) ? null : trim((string) $this->non_resident_corr_bank_swift),
            'non_resident_corr_settlement_account' => blank($this->non_resident_corr_settlement_account) ? null : trim((string) $this->non_resident_corr_settlement_account),
            'non_resident_corr_bank_account' => blank($this->non_resident_corr_bank_account) ? null : trim((string) $this->non_resident_corr_bank_account),
            'cnaps_code' => blank($this->cnaps_code) ? null : trim((string) $this->cnaps_code),
        ];
    }

    /**
     * Поля для печатных форм (${customer.name_en} и т.д.). Значения заполняются только при включённой галочке.
     *
     * @return array<string, string|null>
     */
    public function englishRequisitesPrintPayload(): array
    {
        $keys = [
            'name_en',
            'full_name_en',
            'legal_address_en',
            'actual_address_en',
            'postal_address_en',
            'contact_person_en',
            'bank_name_en',
            'signer_name_nominative_en',
            'signer_name_prepositional_en',
            'signer_position_en',
            'signer_authority_basis_en',
        ];

        $payload = [
            'has_english_requisites' => $this->has_english_requisites ? 'Да' : 'Нет',
        ];

        foreach ($keys as $key) {
            $payload[$key] = null;
        }

        if (! $this->has_english_requisites) {
            return $payload;
        }

        foreach ($keys as $key) {
            $value = $this->{$key};
            $payload[$key] = blank($value) ? null : trim((string) $value);
        }

        return $payload;
    }
}
