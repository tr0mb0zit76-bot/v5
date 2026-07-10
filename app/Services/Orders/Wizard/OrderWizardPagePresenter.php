<?php

namespace App\Services\Orders\Wizard;

use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Services\Commercial\OrderMailContextService;
use App\Services\ContractorCreditService;
use App\Services\DocumentStorageService;
use App\Services\KpiConfigurationService;
use App\Services\OrderDocumentEdoAcknowledgementService;
use App\Services\OrderDocumentRequirementService;
use App\Services\OwnFleetContractorService;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\AtiDictionaryOptionCatalog;
use App\Support\CurrencyDictionary;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\PaymentFormDictionary;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderWizardPagePresenter
{
    public function __construct(
        private readonly OrderWizardOrderPresenter $orderPresenter,
        private readonly ContractorCreditService $creditService,
        private readonly KpiConfigurationService $kpiConfigurationService,
        private readonly OrderDocumentRequirementService $documentRequirementService,
        private readonly OrderDocumentEdoAcknowledgementService $documentEdoAcknowledgementService,
        private readonly OrderMailContextService $orderMailContext,
        private readonly OwnFleetContractorService $ownFleetContractorService,
        private readonly PrintFormTemplateOrderEligibility $printFormTemplateEligibility,
        private readonly DocumentStorageService $documentStorageService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $orderTemplate
     * @return array<string, mixed>
     */
    public function props(
        Request $request,
        ?Order $order,
        ?array $orderTemplate,
        bool $canManageOrderDocuments,
        bool $canApproveOrderDocuments,
    ): array {
        $user = $request->user();
        $contractors = $this->loadRelevantContractors($order);
        $this->attachContractorDebtLimits($contractors);
        $canAccessMail = $this->orderMailContext->userCanAccessMail($user);

        return [
            'order' => $order === null
                ? null
                : $this->orderPresenter->present($request, $order, $canManageOrderDocuments, $canApproveOrderDocuments),
            'orderTemplate' => $order === null ? $orderTemplate : null,
            'contractors' => $contractors->values(),
            'ownCompanies' => $this->loadOwnCompaniesForWizard($order)->values(),
            'ownFleetContractor' => $this->ownFleetContractorPayload(),
            'cargoTypeOptions' => $this->atiDictionaryOptions('cargo_type', $this->fallbackCargoTypeOptions()),
            'packageTypeOptions' => $this->atiDictionaryOptions('pack_type', AtiDictionaryOptionCatalog::fallbackPackageTypeOptions()),
            'loadingTypeOptions' => $this->atiDictionaryOptions('loading_type', $this->fallbackLoadingTypeOptions()),
            'truckBodyTypeOptions' => $this->atiDictionaryOptions('truck_body_type', $this->fallbackTruckBodyTypeOptions()),
            'trailerTypeOptions' => $this->atiDictionaryOptions('trailer_type', $this->fallbackTrailerTypeOptions()),
            'currencyOptions' => CurrencyDictionary::options(),
            'paymentFormOptions' => PaymentFormDictionary::options(),
            'defaultClientPaymentFormCode' => PaymentFormDictionary::defaultClientVatCode(),
            'documentTypeOptions' => $this->documentRequirementService->documentTypeOptions(),
            'documentPartyOptions' => $this->documentRequirementService->partyOptions(),
            'requiredDocumentRules' => $order === null
                ? $this->documentRequirementService->requirementRules()
                : $this->documentRequirementService->requirementRulesForOrder($order),
            'requiredDocumentChecklist' => $this->documentRequirementService->checklistForOrder($order),
            'documentEdoAcknowledgements' => $order === null
                ? []
                : $this->documentEdoAcknowledgementService->serializeForOrder($order),
            'canEditDocumentEdoAcknowledgements' => RoleAccess::canEditDocumentEdoAcknowledgements($user),
            'bonusMultiplier' => $this->kpiConfigurationService->getBonusMultiplier(),
            'orderStatusOptions' => $this->orderStatusOptions(),
            'documentStatusOptions' => $this->documentStatusOptions(),
            'printFormTemplateCatalog' => $this->printFormTemplateCatalog()->values(),
            'printFormTemplateOptions' => $this->availablePrintFormTemplates($order)->values(),
            'printFormTemplateOptionsCustomer' => $this->availablePrintFormTemplates($order, 'customer')->values(),
            'printFormTemplateOptionsCarrier' => $this->availablePrintFormTemplates($order, 'carrier')->values(),
            'orderDocumentWorkflow' => [
                'status_options' => OrderDocumentWorkflowStatus::options(),
            ],
            'documentStorage' => $this->printWorkflowDocumentStorageMeta(),
            'currentUser' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'role_name' => $user?->loadMissing('role')->role?->name,
            ],
            'responsibleUsers' => $this->responsibleUsers($request)->values(),
            'canAssignResponsible' => $this->canAssignResponsible($request),
            'recentIntakeDrafts' => [],
            'cargoTitleSuggestions' => $this->cargoTitleSuggestions(),
            'canAccessMail' => $canAccessMail,
            'canViewOrderTimeline' => $user?->isAdmin() ?? false,
            'orderMailThreads' => $order !== null && $canAccessMail && $user !== null
                ? $this->orderMailContext->threadSummariesForOrder($user, $order)
                : [],
            'mailComposeDefaults' => $order !== null && $canAccessMail
                ? $this->orderMailContext->composeDefaultsForOrder($order)
                : null,
        ];
    }

    public function isTemplateAvailableForOrder(
        PrintFormTemplate $template,
        Order $order,
        ?string $party = null,
        ?bool $isInternationalTransport = null,
    ): bool {
        return $this->printFormTemplateEligibility->isTemplateAvailableForOrder(
            $template,
            $order,
            $party,
            $isInternationalTransport,
        );
    }

    /**
     * @return array{id: int, name: string, inn: string|null, is_own_company: bool}|null
     */
    private function ownFleetContractorPayload(): ?array
    {
        $contractor = $this->ownFleetContractorService->ensureContractor();
        if ($contractor === null) {
            return null;
        }

        return [
            'id' => (int) $contractor->id,
            'name' => (string) $contractor->name,
            'inn' => $contractor->inn !== null ? (string) $contractor->inn : null,
            'is_own_company' => (bool) ($contractor->is_own_company ?? false),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function orderStatusOptions(): array
    {
        return [
            ['value' => 'new', 'label' => 'Новый заказ'],
            ['value' => 'in_progress', 'label' => 'Выполняется'],
            ['value' => 'documents', 'label' => 'Документы'],
            ['value' => 'payment', 'label' => 'Оплата'],
            ['value' => 'closed', 'label' => 'Завершено'],
            ['value' => 'draft', 'label' => 'Черновик (legacy)'],
            ['value' => 'pending', 'label' => 'На согласовании (legacy)'],
            ['value' => 'confirmed', 'label' => 'Подтвержден (legacy)'],
            ['value' => 'completed', 'label' => 'Завершен (legacy)'],
            ['value' => 'cancelled', 'label' => 'Отменена'],
            ['value' => 'disruption', 'label' => 'Срыв'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function documentStatusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Черновик'],
            ['value' => 'pending', 'label' => 'Ожидает'],
            ['value' => 'signed', 'label' => 'Подписан'],
            ['value' => 'sent', 'label' => 'Отправлен'],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function cargoTitleSuggestions(): Collection
    {
        return Cargo::query()
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->distinct()
            ->orderBy('title')
            ->limit(30)
            ->pluck('title')
            ->values();
    }

    /**
     * @param  Collection<int, Contractor>  $contractors
     */
    private function attachContractorDebtLimits(Collection $contractors): void
    {
        $contractorsWithLimit = $contractors
            ->filter(fn (Contractor $contractor): bool => ($contractor->stop_on_limit ?? false) && $contractor->debt_limit !== null);

        if ($contractorsWithLimit->isEmpty()) {
            return;
        }

        $debtMap = $this->creditService->currentDebtByContractorIds(
            $contractorsWithLimit->pluck('id')->all()
        );

        $contractors->transform(function (Contractor $contractor) use ($debtMap): Contractor {
            if (isset($debtMap[$contractor->id])) {
                $contractor->setAttribute('current_debt', $debtMap[$contractor->id]);
                $contractor->setAttribute(
                    'debt_limit_reached',
                    $this->creditService->isBlockedByDebtLimit($contractor, $debtMap[$contractor->id])
                );
            }

            return $contractor;
        });
    }

    /**
     * @return list<string>
     */
    private function contractorSelectColumns(): array
    {
        $columns = ['id', 'name', 'inn', 'phone', 'email', 'type'];

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $columns[] = 'is_own_company';
        }

        if (Schema::hasColumn('contractors', 'full_name')) {
            $columns[] = 'full_name';
        }

        foreach ([
            'debt_limit',
            'debt_limit_currency',
            'stop_on_limit',
            'default_customer_payment_form',
            'default_customer_payment_term',
            'default_customer_payment_schedule',
            'default_carrier_payment_form',
            'default_carrier_payment_term',
            'default_carrier_payment_schedule',
            'cooperation_terms_notes',
            'default_customer_norms_penalties',
            'default_carrier_norms_penalties',
            'ogrn',
            'bank_name',
            'bik',
            'account_number',
            'correspondent_account',
            'bank_accounts',
            'signer_name_nominative',
            'signer_name_prepositional',
            'signer_authority_basis',
        ] as $column) {
            if (Schema::hasColumn('contractors', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function printFormTemplateCatalog(): Collection
    {
        if (! Schema::hasTable('print_form_templates')) {
            return collect();
        }

        $query = PrintFormTemplate::query()
            ->where('entity_type', 'order')
            ->where('is_active', true)
            ->whereNotNull('file_path');

        if (Schema::hasColumn('print_form_templates', 'contractor_id')) {
            $query->with(['contractor:id,name']);
        }

        if (Schema::hasColumn('print_form_templates', 'own_company_id')) {
            $query->with(['ownCompany:id,name']);
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('document_type')
            ->orderBy('name')
            ->get()
            ->map(fn (PrintFormTemplate $template): array => $this->printFormTemplateEligibility->templateToArray($template))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function availablePrintFormTemplates(?Order $order = null, ?string $party = null): Collection
    {
        if (! Schema::hasTable('print_form_templates')) {
            return collect();
        }

        $ownCompanyId = $order?->own_company_id !== null ? (int) $order->own_company_id : null;
        $isInternational = $order !== null
            ? $order->isInternationalTransportEffective()
            : false;
        $contractorIds = $order !== null ? $this->printFormTemplateEligibility->contractorIdsForOrder($order) : [];

        $filtered = $this->printFormTemplateCatalog()
            ->filter(fn (array $template): bool => $this->printFormTemplateEligibility->isArrayTemplateAvailableForContext(
                $template,
                $ownCompanyId,
                $isInternational,
                $party,
                $contractorIds,
            ));

        return $this->printFormTemplateEligibility
            ->preferOwnCompanySpecificTemplates($filtered, $ownCompanyId)
            ->sortByDesc(fn (array $template): int => $this->printFormTemplateEligibility->specificityScore(
                $template,
                $ownCompanyId,
                $isInternational,
            ))
            ->values();
    }

    /**
     * @return list<int>
     */
    private function orderTemplateContractorIds(?Order $order): array
    {
        if ($order === null) {
            return [];
        }

        $ids = collect([
            $order->customer_id,
            $order->carrier_id,
            $order->own_company_id,
        ]);

        if ($order->relationLoaded('legs') && Schema::hasTable('leg_contractor_assignments')) {
            foreach ($order->legs as $leg) {
                if ($leg->relationLoaded('contractorAssignments')) {
                    foreach ($leg->contractorAssignments as $assignment) {
                        if ($assignment->contractor_id !== null) {
                            $ids->push($assignment->contractor_id);
                        }
                    }
                } else {
                    $cid = $leg->contractorAssignment?->contractor_id;
                    if ($cid !== null) {
                        $ids->push($cid);
                    }
                }
            }
        }

        $savedPerformers = is_array($order->performers) ? $order->performers : [];
        foreach ($savedPerformers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (is_array($slot) && isset($slot['contractor_id']) && $slot['contractor_id'] !== null) {
                        $ids->push($slot['contractor_id']);
                    }
                }

                continue;
            }

            if (isset($performer['contractor_id']) && $performer['contractor_id'] !== null) {
                $ids->push($performer['contractor_id']);
            }
        }

        return $ids->filter(fn (mixed $value): bool => is_int($value) || ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{value:int, code:string, label:string}>  $fallback
     * @return list<array{value:int, code:string|null, label:string, ati_id:int|null}>
     */
    private function atiDictionaryOptions(string $dictionary, array $fallback): array
    {
        return AtiDictionaryOptionCatalog::options($dictionary, $fallback);
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    private function fallbackCargoTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'general', 'label' => 'Общий груз'],
            ['value' => 2, 'code' => 'dangerous', 'label' => 'Опасный груз'],
            ['value' => 3, 'code' => 'temperature_controlled', 'label' => 'Температурный режим'],
            ['value' => 4, 'code' => 'oversized', 'label' => 'Негабаритный груз'],
            ['value' => 5, 'code' => 'fragile', 'label' => 'Хрупкий груз'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    private function fallbackLoadingTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'rear', 'label' => 'Задняя'],
            ['value' => 2, 'code' => 'side', 'label' => 'Боковая'],
            ['value' => 3, 'code' => 'top', 'label' => 'Верхняя'],
            ['value' => 4, 'code' => 'full', 'label' => 'Полная растентовка'],
            ['value' => 5, 'code' => 'tail_lift', 'label' => 'Гидроборт'],
            ['value' => 6, 'code' => 'crane', 'label' => 'Манипулятор'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    private function fallbackTruckBodyTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'all_closed', 'label' => 'Все закрытые'],
            ['value' => 2, 'code' => 'all_open', 'label' => 'Все открытые'],
            ['value' => 3, 'code' => 'tent', 'label' => 'Тент'],
            ['value' => 4, 'code' => 'isothermal', 'label' => 'Изотерм'],
            ['value' => 5, 'code' => 'refrigerator', 'label' => 'Рефрижератор'],
            ['value' => 6, 'code' => 'container', 'label' => 'Контейнеровоз'],
            ['value' => 7, 'code' => 'flatbed', 'label' => 'Бортовой'],
            ['value' => 8, 'code' => 'all_metal', 'label' => 'Цельнометаллический'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    private function fallbackTrailerTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'semi_trailer', 'label' => 'Полуприцеп'],
            ['value' => 2, 'code' => 'trailer', 'label' => 'Прицеп'],
            ['value' => 3, 'code' => 'road_train', 'label' => 'Автопоезд'],
            ['value' => 4, 'code' => 'solo', 'label' => 'Одиночная машина'],
        ];
    }

    /**
     * Все «свои компании» из справочника (флаг is_own_company), без лимита и visibleTo.
     *
     * @return Collection<int, Contractor>
     */
    private function loadOwnCompaniesForWizard(?Order $order): Collection
    {
        if (! Schema::hasColumn('contractors', 'is_own_company')) {
            return collect();
        }

        $ensureIds = [];
        if ($order?->own_company_id) {
            $ensureIds[] = (int) $order->own_company_id;
        }

        return Contractor::query()
            ->where(function ($query) use ($ensureIds): void {
                $query->ownCompanyProfiles();

                if ($ensureIds !== []) {
                    $query->orWhereIn('id', $ensureIds);
                }
            })
            ->orderBy('name')
            ->get($this->contractorSelectColumns());
    }

    /**
     * Оптимизированная загрузка контрагентов: только нужные для текущего заказа
     *
     * @return Collection<int, Contractor>
     */
    private function loadRelevantContractors(?Order $order): Collection
    {
        $user = auth()->user();
        $relatedIds = $order !== null ? $this->getRelatedContractorIds($order) : [];

        $query = Contractor::query()->visibleTo($user, null, $relatedIds);

        $query->where(function ($q) use ($relatedIds): void {
            $q->where('is_active', true);

            if (Schema::hasColumn('contractors', 'is_own_company')) {
                $q->orWhere('is_own_company', true);
            }

            if ($relatedIds !== []) {
                $q->orWhereIn('id', $relatedIds);
            }
        });

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $query->orderByDesc('is_own_company');
        }

        return $query->orderBy('name')
            ->limit($order !== null ? 300 : 200)
            ->get($this->contractorSelectColumns());
    }

    /**
     * @return list<int>
     */
    private function getRelatedContractorIds(Order $order): array
    {
        $ids = [];

        if ($order->customer_id) {
            $ids[] = $order->customer_id;
        }
        if ($order->carrier_id) {
            $ids[] = $order->carrier_id;
        }
        if ($order->own_company_id) {
            $ids[] = $order->own_company_id;
        }

        if (Schema::hasTable('leg_contractor_assignments') && $order->relationLoaded('legs')) {
            foreach ($order->legs as $leg) {
                $contractorId = $leg->contractorAssignment?->contractor_id;
                if ($contractorId) {
                    $ids[] = $contractorId;
                }
            }
        }

        if (Schema::hasTable('financial_terms')) {
            $financialTerm = $order->financialTerms->first();
            if ($financialTerm && $financialTerm->contractors_costs) {
                $costs = is_array($financialTerm->contractors_costs)
                    ? $financialTerm->contractors_costs
                    : json_decode($financialTerm->contractors_costs, true) ?? [];

                foreach ($costs as $cost) {
                    if (! empty($cost['contractor_id'])) {
                        $ids[] = $cost['contractor_id'];
                    }
                }
            }

            if ($financialTerm && is_array($financialTerm->additional_costs)) {
                foreach ($financialTerm->additional_costs as $cost) {
                    if (is_array($cost) && ! empty($cost['contractor_id'])) {
                        $ids[] = (int) $cost['contractor_id'];
                    }
                }
            }
        }

        if (Schema::hasColumn('orders', 'wizard_state')) {
            $wizardPayload = $order->wizard_state;
            if (is_array($wizardPayload)) {
                $wizardCosts = data_get($wizardPayload, 'financial_term.contractors_costs', []);
                if (is_array($wizardCosts)) {
                    foreach ($wizardCosts as $cost) {
                        if (is_array($cost) && ! empty($cost['contractor_id'])) {
                            $ids[] = (int) $cost['contractor_id'];
                        }
                    }
                }

                $wizardAdditionalCosts = data_get($wizardPayload, 'financial_term.additional_costs', []);
                if (is_array($wizardAdditionalCosts)) {
                    foreach ($wizardAdditionalCosts as $cost) {
                        if (is_array($cost) && ! empty($cost['contractor_id'])) {
                            $ids[] = (int) $cost['contractor_id'];
                        }
                    }
                }
                $wizardPerformers = $wizardPayload['performers'] ?? [];
                if (is_array($wizardPerformers)) {
                    foreach ($wizardPerformers as $performer) {
                        if (is_array($performer) && ! empty($performer['contractor_id'])) {
                            $ids[] = (int) $performer['contractor_id'];
                        }
                    }
                }
            }
        }

        return array_unique(array_filter($ids));
    }

    /**
     * @return array{driver: string, label: string, folder_hint: string}
     */
    private function printWorkflowDocumentStorageMeta(): array
    {
        $driver = $this->documentStorageService->configuredDriver();
        $label = $driver === DocumentStorageService::DRIVER_NEXTCLOUD
            ? 'Nextcloud (WebDAV)'
            : 'локальное хранилище приложения';

        return [
            'driver' => $driver,
            'label' => $label,
            'folder_hint' => $this->printWorkflowStorageFolderHint($driver),
        ];
    }

    private function printWorkflowStorageFolderHint(string $driver): string
    {
        if ($driver === DocumentStorageService::DRIVER_NEXTCLOUD) {
            $root = trim(str_replace('\\', '/', (string) config('document_storage.nextcloud.webdav_root', '')), '/');
            $parts = array_values(array_filter(explode('/', $root), static fn (string $part): bool => $part !== ''));
            $filesIndex = array_search('files', $parts, true);
            $tail = $filesIndex !== false
                ? array_slice($parts, $filesIndex + 2)
                : [];
            $prefix = $tail !== [] ? implode('/', $tail).'/' : '';

            return $prefix.'order_documents/{номер_заказа}/';
        }

        return 'storage/app/private/order_documents/{номер_заказа}/';
    }

    private function canAssignResponsible(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders');
    }

    /**
     * @return Collection<int, array{id:int,name:string}>
     */
    private function responsibleUsers(Request $request): Collection
    {
        $user = $request->user();

        if ($user === null) {
            return collect();
        }

        if (! $this->canAssignResponsible($request)) {
            return collect([[
                'id' => $user->id,
                'name' => $user->name,
            ]]);
        }

        $usersQuery = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.name', 'manager')
            ->orderBy('users.name');

        if (Schema::hasColumn('users', 'is_active')) {
            $usersQuery->where('users.is_active', true);
        }

        $users = $usersQuery
            ->get(['users.id', 'users.name'])
            ->map(fn ($userRow): array => ['id' => $userRow->id, 'name' => $userRow->name])
            ->values();

        $currentUserId = (int) $user->id;
        if (! $users->contains(fn (array $row): bool => (int) $row['id'] === $currentUserId)) {
            $users->prepend([
                'id' => $user->id,
                'name' => $user->name,
            ]);
        }

        if ($users->isNotEmpty()) {
            return $users;
        }

        return collect([[
            'id' => $user->id,
            'name' => $user->name,
        ]]);
    }
}
