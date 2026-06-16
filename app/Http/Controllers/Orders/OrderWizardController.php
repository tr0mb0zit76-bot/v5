<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInlineOrderContractorRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateInlineOrderFieldRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\AtiDictionaryItem;
use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\LegContractorAssignment;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Services\Commercial\OrderMailContextService;
use App\Services\ContractorCreditService;
use App\Services\DaDataService;
use App\Services\DocumentStorageService;
use App\Services\KpiConfigurationService;
use App\Services\OrderCompensationService;
use App\Services\OrderDocumentRequirementService;
use App\Services\OrderIntakeGoldenLibraryService;
use App\Services\OrderNumberingService;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Services\OrderPrintFormDraftService;
use App\Services\Orders\OrderBasedOnTemplateBuilder;
use App\Services\Orders\OrderInlineFieldUpdateService;
use App\Services\OrderWizardService;
use App\Services\OwnFleetContractorService;
use App\Services\PaymentSettlementSummaryBuilder;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\CargoPerformerAllocationNormalizer;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CashToCashMarginCalculator;
use App\Support\ContractorCostRowClassification;
use App\Support\ContractorIdentity;
use App\Support\CurrencyDictionary;
use App\Support\OrderAdditionalCostNormalizer;
use App\Support\OrderAgentLexicon;
use App\Support\OrderCargoItemsPayloadNormalizer;
use App\Support\OrderDeleteAuthorization;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\OrderFinancialEditAuthorization;
use App\Support\OrderPaymentTermsConfigResolver;
use App\Support\OrderPrintWorkflowLock;
use App\Support\OwnFleetCatalog;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PerformerRouteActualDates;
use App\Support\RoleAccess;
use App\Support\RoutePointNormalizedData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;

class OrderWizardController extends Controller
{
    public function create(Request $request, OrderBasedOnTemplateBuilder $orderBasedOnTemplateBuilder): Response
    {
        $orderTemplate = null;

        if ($request->filled('from')) {
            $sourceOrder = Order::query()->find((int) $request->query('from'));

            if ($sourceOrder instanceof Order && $this->userCanUseOrderAsTemplate($request, $sourceOrder)) {
                $orderTemplate = $orderBasedOnTemplateBuilder->build($sourceOrder);
            }
        }

        return $this->renderPage($request, null, $orderTemplate);
    }

    public function suggestOrderNumber(Request $request, OrderNumberingService $orderNumbering): JsonResponse
    {
        $ownCompanyId = $request->integer('own_company_id');
        $ownCompany = $ownCompanyId > 0
            ? Contractor::query()
                ->where('is_own_company', true)
                ->find($ownCompanyId)
            : null;

        return response()->json($orderNumbering->preview($ownCompany, null, $request->user()));
    }

    public function store(StoreOrderRequest $request, OrderWizardService $orderWizardService): RedirectResponse
    {
        $validated = $request->validatedForWizard();
        $user = $request->user();

        $order = $orderWizardService->create($validated, $user);

        $draftId = (int) $request->input('intake_draft_id', 0);
        if ($draftId > 0 && $user !== null) {
            app(OrderIntakeGoldenLibraryService::class)->commit(
                $user,
                $draftId,
                $order->id,
                $validated,
            );
        }

        return to_route('orders.edit', $order);
    }

    public function edit(Request $request, Order $order): Response
    {
        return $this->renderPage($request, $this->loadOrderForEditing($order));
    }

    public function update(UpdateOrderRequest $request, Order $order, OrderWizardService $orderWizardService): RedirectResponse
    {
        abort_unless($this->canEditInlineField($request, $order), 403);

        Log::info('orders.update request received', [
            'order_id' => $order->id,
            'user_id' => $request->user()?->id,
            'client_id' => $request->input('client_id'),
            'performers_count' => count((array) $request->input('performers', [])),
            'cargo_allocations_count' => count((array) data_get($request->input('cargo_items.0'), 'performer_allocations', [])),
        ]);

        try {
            $order = $orderWizardService->update($order, $request->validatedForWizard(), $request->user());
        } catch (\Throwable $exception) {
            Log::error('orders.update failed', [
                'order_id' => $order->id,
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Не удалось сохранить заказ: '.$exception->getMessage(),
                ]);
        }

        Log::info('orders.update completed', [
            'order_id' => $order->id,
            'carrier_id' => $order->carrier_id,
            'updated_at' => optional($order->updated_at)?->toDateTimeString(),
        ]);

        return to_route('orders.edit', $order);
    }

    public function inlineUpdate(
        UpdateInlineOrderFieldRequest $request,
        Order $order,
        OrderInlineFieldUpdateService $orderInlineFieldUpdateService,
    ): RedirectResponse {
        abort_unless($this->canEditInlineField($request, $order), 403);

        $payload = $request->validatedPayload();
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $orderInlineFieldUpdateService->apply($user, $order, $payload['field'], $payload['value']);

        return to_route('orders.index');
    }

    public function syncFinancialTermsFromOrderRatesForOrder(Order $order): void
    {
        $this->syncFinancialTermsFromOrderRates($order);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        if ($order->trashed()) {
            return to_route('orders.index');
        }

        abort_unless($this->canDeleteOrder($request, $order), 403);

        DB::transaction(function () use ($order): void {
            $order = $this->loadOrderForEditing($order);
            $cargoItems = $this->orderCargoItems($order);

            DB::table('cargo_leg')
                ->when(
                    $cargoItems->isNotEmpty(),
                    fn ($query) => $query->whereIn('cargo_id', $cargoItems->pluck('id')),
                    fn ($query) => $query->whereRaw('1 = 0')
                )
                ->delete();

            DB::table('route_points')
                ->whereIn('order_leg_id', $order->legs->pluck('id'))
                ->delete();

            $legIds = $order->legs->pluck('id');

            if ($legIds->isNotEmpty()) {
                if (Schema::hasTable('leg_costs')) {
                    DB::table('leg_costs')->whereIn('order_leg_id', $legIds)->delete();
                }

                if (Schema::hasTable('leg_contractor_assignments')) {
                    DB::table('leg_contractor_assignments')->whereIn('order_leg_id', $legIds)->delete();
                }
            }

            if (Schema::hasTable('order_documents')) {
                $order->documents()->delete();
            }

            if (Schema::hasTable('payment_schedule_payment_events')) {
                DB::table('payment_schedule_payment_events')
                    ->where('order_id', $order->id)
                    ->delete();
            }

            if (Schema::hasTable('payment_schedules')) {
                DB::table('payment_schedules')
                    ->where('order_id', $order->id)
                    ->delete();
            }

            if (Schema::hasTable('financial_terms')) {
                $order->financialTerms()->delete();
            }

            if (Schema::hasTable('order_status_logs')) {
                $order->statusLogs()->delete();
            }

            if (Schema::hasColumn('cargos', 'order_id')) {
                $order->cargoItems()->delete();
            } elseif ($cargoItems->isNotEmpty()) {
                Cargo::query()->whereIn('id', $cargoItems->pluck('id'))->delete();
            }

            DB::table('order_legs')
                ->where('order_id', $order->id)
                ->delete();
            $order->delete();
        });

        return to_route('orders.index');
    }

    public function suggestAddress(Request $request, DaDataService $daDataService): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'suggestions' => $daDataService->suggestAddress($request->string('query')->toString()),
        ]);
    }

    public function storeContractor(StoreInlineOrderContractorRequest $request): JsonResponse
    {
        $attributes = [
            'type' => $request->input('type', 'customer'),
            'name' => ContractorIdentity::normalizeName($request->input('name')),
            'inn' => ContractorIdentity::normalizeInn($request->input('inn')),
            'kpp' => $request->string('kpp')->toString() ?: null,
            'legal_address' => $request->string('address')->toString() ?: null,
            'actual_address' => $request->string('address')->toString() ?: null,
            'phone' => $request->string('phone')->toString() ?: null,
            'email' => $request->string('email')->toString() ?: null,
            'contact_person' => $request->string('contact_person')->toString() ?: null,
            'is_active' => true,
            'is_verified' => false,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ];

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $attributes['is_own_company'] = false;
        }

        if (Schema::hasColumn('contractors', 'owner_id')) {
            $attributes['owner_id'] = $request->user()?->id;
        }

        $contractor = Contractor::query()->create($attributes);

        return response()->json([
            'contractor' => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'type' => $contractor->type,
                'is_own_company' => $contractor->is_own_company,
            ],
        ], 201);
    }

    public function calculateCompensation(Request $request, OrderCompensationService $orderCompensationService): JsonResponse
    {
        $request->validate([
            'customer_rate' => ['nullable', 'numeric', 'min:0'],
            'carrier_rate' => ['nullable', 'numeric', 'min:0'],
            'additional_expenses' => ['nullable', 'numeric', 'min:0'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_date' => ['nullable', 'string', 'max:64'],
            'client_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'carrier_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'customer_payment_form' => ['nullable', 'string', 'max:50', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'carrier_payment_form' => ['nullable', 'string', 'max:50', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'contractors_costs' => ['nullable', 'array'],
            'contractors_costs.*.payment_form' => ['nullable', 'string', 'max:50', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
        ]);

        $payload = $request->all();
        $payload['order_date'] = OrderAgentLexicon::normalizeDateValue($payload['order_date'] ?? null);

        $calculation = $orderCompensationService->calculateRealtime($payload);

        return response()->json($calculation);
    }

    public function generateDocumentDraft(
        Request $request,
        Order $order,
        PrintFormTemplate $printFormTemplate,
        OrderPrintFormDraftService $draftService,
        PrintFormDraftResponseBuilder $draftResponseBuilder,
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless($this->canEditInlineField($request, $order), 403);
        abort_if($printFormTemplate->entity_type !== 'order', 422, 'Черновик можно сформировать только для шаблона заказа.');
        abort_if(blank($printFormTemplate->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');

        $orderForCheck = $this->loadOrderForEditing($order);
        $isInternationalTransport = $request->has('is_international_transport')
            ? $request->boolean('is_international_transport')
            : null;
        $party = $request->query('print_party');
        $party = is_string($party) && in_array($party, ['customer', 'carrier'], true) ? $party : null;

        abort_unless(
            $this->isTemplateAvailableForOrder($printFormTemplate, $orderForCheck, $party, $isInternationalTransport),
            422,
            'Шаблон недоступен для этого заказа. Проверьте тип перевозки (ВЭД), нашу компанию и перевозчика.'
        );

        $generatedFile = $draftService->generate($printFormTemplate, $orderForCheck);

        return $draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
    }

    /**
     * @param  array<string, mixed>|null  $orderTemplate
     */
    private function renderPage(Request $request, ?Order $order = null, ?array $orderTemplate = null): Response
    {
        /** @var ContractorCreditService $creditService */
        $creditService = app(ContractorCreditService::class);
        $kpiConfigurationService = app(KpiConfigurationService::class);
        $documentRequirementService = app(OrderDocumentRequirementService::class);

        // Оптимизация: загружаем только нужных контрагентов
        $contractors = $this->loadRelevantContractors($order);

        // Оптимизация: рассчитываем долги ТОЛЬКО для контрагентов с лимитом
        $contractorsWithLimit = $contractors
            ->filter(fn (Contractor $contractor): bool => ($contractor->stop_on_limit ?? false) && $contractor->debt_limit !== null);

        if ($contractorsWithLimit->isNotEmpty()) {
            $debtMap = $creditService->currentDebtByContractorIds(
                $contractorsWithLimit->pluck('id')->all()
            );

            $contractors->transform(function (Contractor $contractor) use ($creditService, $debtMap): Contractor {
                if (isset($debtMap[$contractor->id])) {
                    $contractor->setAttribute('current_debt', $debtMap[$contractor->id]);
                    $contractor->setAttribute('debt_limit_reached',
                        $creditService->isBlockedByDebtLimit($contractor, $debtMap[$contractor->id])
                    );
                }

                return $contractor;
            });
        }

        $user = $request->user();
        $isSignerOnly = $user !== null
            && $user->hasSigningAuthority()
            && ! ($user->isAdmin() || $user->isSupervisor());

        $canManageOrderDocuments = $order !== null
            && OrderDocumentAccessAuthorization::userMayManageDocuments($user, $order)
            && ! $isSignerOnly;
        $canApproveOrderDocuments = $user !== null
            && $order !== null
            && $user->canSignDocumentsForOwnCompany($order->own_company_id);

        $orderMailContext = app(OrderMailContextService::class);
        $canAccessMail = $orderMailContext->userCanAccessMail($user);

        return Inertia::render('Orders/Wizard', [
            'order' => $order === null ? null : $this->serializeOrder($request, $order, $canManageOrderDocuments, $canApproveOrderDocuments),
            'orderTemplate' => $order === null ? $orderTemplate : null,
            'contractors' => $contractors->values(),
            'ownCompanies' => $this->loadOwnCompaniesForWizard($order)->values(),
            'ownFleetContractor' => $this->ownFleetContractorPayload(),
            'cargoTypeOptions' => $this->atiDictionaryOptions('cargo_type', $this->fallbackCargoTypeOptions()),
            'packageTypeOptions' => $this->atiDictionaryOptions('pack_type', $this->fallbackPackageTypeOptions()),
            'loadingTypeOptions' => $this->atiDictionaryOptions('loading_type', $this->fallbackLoadingTypeOptions()),
            'truckBodyTypeOptions' => $this->atiDictionaryOptions('truck_body_type', $this->fallbackTruckBodyTypeOptions()),
            'trailerTypeOptions' => $this->atiDictionaryOptions('trailer_type', $this->fallbackTrailerTypeOptions()),
            'currencyOptions' => CurrencyDictionary::options(),
            'paymentFormOptions' => PaymentFormDictionary::options(),
            'defaultClientPaymentFormCode' => PaymentFormDictionary::defaultClientVatCode(),
            'documentTypeOptions' => $documentRequirementService->documentTypeOptions(),
            'documentPartyOptions' => $documentRequirementService->partyOptions(),
            'requiredDocumentRules' => $order === null
                ? $documentRequirementService->requirementRules()
                : $documentRequirementService->requirementRulesForOrder($order),
            'requiredDocumentChecklist' => $documentRequirementService->checklistForOrder($order),
            'bonusMultiplier' => $kpiConfigurationService->getBonusMultiplier(),
            'orderStatusOptions' => [
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
            ],
            'documentStatusOptions' => [
                ['value' => 'draft', 'label' => 'Черновик'],
                ['value' => 'pending', 'label' => 'Ожидает'],
                ['value' => 'signed', 'label' => 'Подписан'],
                ['value' => 'sent', 'label' => 'Отправлен'],
            ],
            'printFormTemplateCatalog' => $this->printFormTemplateCatalog()->values(),
            'printFormTemplateOptions' => $this->availablePrintFormTemplates($order)->values(),
            'printFormTemplateOptionsCustomer' => $this->availablePrintFormTemplates($order, 'customer')->values(),
            'printFormTemplateOptionsCarrier' => $this->availablePrintFormTemplates($order, 'carrier')->values(),
            'orderDocumentWorkflow' => [
                'status_options' => OrderDocumentWorkflowStatus::options(),
            ],
            'documentStorage' => $this->printWorkflowDocumentStorageMeta(),
            'currentUser' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
                'role_name' => $request->user()?->loadMissing('role')->role?->name,
            ],
            'recentIntakeDrafts' => [],
            'cargoTitleSuggestions' => Cargo::query()
                ->whereNotNull('title')
                ->where('title', '!=', '')
                ->distinct()
                ->orderBy('title')
                ->limit(30)
                ->pluck('title')
                ->values(),
            'canAccessMail' => $canAccessMail,
            'canViewOrderTimeline' => $user?->isAdmin() ?? false,
            'orderMailThreads' => $order !== null && $canAccessMail && $user !== null
                ? $orderMailContext->threadSummariesForOrder($user, $order)
                : [],
            'mailComposeDefaults' => $order !== null && $canAccessMail
                ? $orderMailContext->composeDefaultsForOrder($order)
                : null,
        ]);
    }

    /**
     * @return array{id: int, name: string, inn: string|null, is_own_company: bool}|null
     */
    private function ownFleetContractorPayload(): ?array
    {
        $contractor = app(OwnFleetContractorService::class)->ensureContractor();
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

    private function canDeleteOrder(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $user->loadMissing('role');

        return OrderDeleteAuthorization::userMayDelete(
            $user->role?->name,
            $user->id,
            (int) $order->manager_id,
            $order->manual_status,
            $order->status,
        );
    }

    /**
     * @return array{customer: array<string, mixed>, carrier: array<string, mixed>}|null
     */
    private function serializeBasicTermsForWizard(Order $order): ?array
    {
        /** @var PrintFormBasicTermsService $service */
        $service = app(PrintFormBasicTermsService::class);

        if (! $service->tablesReady()) {
            return null;
        }

        return [
            'customer' => $service->wizardPayloadForOrder($order, PrintFormBasicTerm::PARTY_CUSTOMER),
            'carrier' => $service->wizardPayloadForOrder($order, PrintFormBasicTerm::PARTY_CARRIER),
        ];
    }

    private function canPromoteBasicTerms(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return RoleAccess::canAccessVisibilityArea($user, 'contractors')
            || RoleAccess::canAccessSettingsSystem($user);
    }

    private function canDirectPromoteBasicTerms(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return app(ContractorPrintFormChangeRequestService::class)->canDirectManagePrintForm($user);
    }

    public function canEditOrder(Request $request, Order $order): bool
    {
        return $this->canEditInlineField($request, $order);
    }

    private function canEditInlineField(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        if ((int) $order->manager_id !== (int) $user->id) {
            return false;
        }

        return ! OrderPrintWorkflowLock::allPrintWorkflowDocumentsFinalized($order);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Request $request, Order $order, bool $canManageOrderDocuments, bool $canApproveOrderDocuments): array
    {
        if (Schema::hasTable('payment_schedules')) {
            PaymentScheduleAutomaticStatus::refreshForOrder((int) $order->id);
        }

        $financialTerm = Schema::hasTable('financial_terms') ? $order->financialTerms->first() : null;
        $wizardState = $this->resolveWizardState($order);
        $useWizardState = is_array($wizardState) && filled($wizardState['financial_term'] ?? null);
        /** @var array<string, mixed> $wizardFt */
        $wizardFt = $useWizardState ? ($wizardState['financial_term'] ?? []) : [];

        $paymentTermsRaw = null;
        if (! $useWizardState) {
            if (Schema::hasColumn('orders', 'payment_terms')) {
                $paymentTermsRaw = $order->getAttribute('payment_terms');
            }
            if (blank($paymentTermsRaw) && $financialTerm !== null && Schema::hasColumn('financial_terms', 'payment_terms_snapshot')) {
                $paymentTermsRaw = $financialTerm->payment_terms_snapshot;
            }
        }

        if ($useWizardState) {
            $paymentTermsConfig = [
                'client' => [
                    'request_mode' => $wizardFt['client_request_mode'] ?? 'single_request',
                    'payment_schedule' => $wizardFt['client_payment_schedule'] ?? [],
                ],
            ];
        } else {
            $paymentTermsConfig = $this->decodePaymentTermsConfig($paymentTermsRaw);
        }
        $routePointHasAddressColumn = Schema::hasColumn('route_points', 'address');
        $routePointHasMetadataColumn = Schema::hasColumn('route_points', 'metadata');
        $cargoItems = $this->orderCargoItems($order);
        $documents = Schema::hasTable('order_documents')
            ? $order->documents
                ->reject(fn (OrderDocument $document): bool => $this->isEmptyPrintWorkflowArtifact($document))
                ->values()
            : collect();
        $templateIds = $documents->pluck('template_id')->filter()->unique()->values()->all();
        /** @var Collection<int, PrintFormTemplate> $templatesById */
        $templatesById = collect();
        if ($templateIds !== [] && Schema::hasTable('print_form_templates')) {
            $templatesById = PrintFormTemplate::query()->whereIn('id', $templateIds)->get()->keyBy('id');
        }
        $statusLogs = Schema::hasTable('order_status_logs') ? $order->statusLogs : collect();
        $routePoints = $order->legs
            ->sortBy('sequence')
            ->flatMap(function ($leg) use ($routePointHasAddressColumn) {
                return $leg->routePoints
                    ->sortBy('sequence')
                    ->map(fn ($point): array => [
                        'id' => $point->id,
                        'stage' => $this->normalizeStageIdentifierForWizard((string) $leg->description),
                        'leg_sequence' => $leg->sequence,
                        'type' => $point->type,
                        'sequence' => $point->sequence,
                        'address' => $routePointHasAddressColumn && filled($point->address)
                            ? $point->address
                            : data_get($point->metadata, 'address',
                                data_get($point->metadata, 'full_address',
                                    data_get($point->normalized_data, 'result', $point->instructions))),
                        'normalized_data' => RoutePointNormalizedData::resolveForWizard($point),
                        'planned_date' => optional($point->planned_date)?->toDateString(),
                        'planned_time_from' => filled($point->planned_time_from) ? substr((string) $point->planned_time_from, 0, 5) : null,
                        'planned_time_to' => filled($point->planned_time_to) ? substr((string) $point->planned_time_to, 0, 5) : null,
                        'actual_date' => optional($point->actual_date)?->toDateString(),
                        'actual_time' => filled($point->actual_time) ? substr((string) $point->actual_time, 0, 5) : null,
                        'contact_person' => $point->contact_person,
                        'contact_phone' => $point->contact_phone,
                        'sender_name' => $point->sender_name,
                        'sender_contact' => $point->sender_contact,
                        'sender_phone' => $point->sender_phone,
                        'recipient_name' => $point->recipient_name,
                        'recipient_contact' => $point->recipient_contact,
                        'recipient_phone' => $point->recipient_phone,
                    ]);
            })
            ->values()
            ->all();

        $performers = $this->serializePerformersPayload($order, $financialTerm);
        if ($useWizardState && is_array($wizardState) && filled($wizardState['performers'] ?? null)) {
            $performers = $this->mergePerformersFromWizardState($performers, $wizardState['performers']);
        }

        $performers = PerformerRouteActualDates::hydratePerformersFromRoutePoints($performers, $routePoints);
        $performers = $this->performersPayloadWithFleetLabels($performers);

        $financialTermForNormalize = $financialTerm;
        if ($useWizardState) {
            $wizardCosts = $wizardFt['contractors_costs'] ?? [];
            if (! is_array($wizardCosts)) {
                $wizardCosts = [];
            }

            if ($wizardCosts === []) {
                // Пустой снимок в wizard_state не должен затирать детализацию из financial_terms.
                $financialTermForNormalize = $financialTerm;
            } else {
                $dbCosts = is_array($financialTerm?->contractors_costs) ? $financialTerm->contractors_costs : [];
                $mergedCosts = $this->mergeContractorsCostsSnapshots($dbCosts, $wizardCosts);

                $financialTermForNormalize = new FinancialTerm([
                    'contractors_costs' => $mergedCosts,
                    'client_currency' => $wizardFt['client_currency'] ?? $financialTerm?->client_currency ?? 'RUB',
                ]);
            }
        }

        $contractorsCostsRaw = collect($this->normalizeContractorsCosts($order, $financialTermForNormalize, $performers))
            ->values()
            ->all();

        [$contractorsCosts, $migratedAdditionalCosts] = OrderAdditionalCostNormalizer::partitionContractorsCosts(
            $contractorsCostsRaw,
            (string) ($financialTerm?->client_currency ?? 'RUB'),
            optional($order->additional_expenses_payment_date)?->toDateString(),
        );

        $contractorsCosts = collect($contractorsCosts)
            ->map(fn (array $cost): array => [
                'stage' => $cost['stage'] ?? null,
                'carrier_slot' => $cost['carrier_slot'] ?? null,
                'contractor_id' => $cost['contractor_id'] ?? null,
                'amount' => $cost['amount'] ?? null,
                'currency' => $cost['currency'] ?? 'RUB',
                'payment_form' => $this->normalizePaymentFormCodeForWizard($cost['payment_form'] ?? null, 'no_vat'),
                'payment_schedule' => $cost['payment_schedule'] ?? [],
                'payment_terms' => $cost['payment_terms'] ?? '',
            ])
            ->values()
            ->all();

        $additionalCostsSource = $useWizardState
            ? $this->mergeAdditionalCostsSnapshots(
                is_array($financialTerm?->additional_costs) ? $financialTerm->additional_costs : [],
                is_array($wizardFt['additional_costs'] ?? null) ? $wizardFt['additional_costs'] : [],
            )
            : (is_array($financialTerm?->additional_costs) ? $financialTerm->additional_costs : []);

        $additionalCosts = OrderAdditionalCostNormalizer::normalizeList(
            array_merge(
                is_array($additionalCostsSource) ? $additionalCostsSource : [],
                $migratedAdditionalCosts,
            ),
            (string) ($financialTerm?->client_currency ?? 'RUB'),
            optional($order->additional_expenses_payment_date)?->toDateString(),
        );

        return [
            'can_edit_order' => $this->canEditInlineField($request, $order),
            'can_view_order_documents' => OrderDocumentAccessAuthorization::userMayViewDocuments($request->user(), $order),
            'can_manage_order_documents' => $canManageOrderDocuments,
            'can_edit_financial_fields' => OrderFinancialEditAuthorization::userMayEditFinancialFields($request->user(), $order),
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'manual_status' => Schema::hasColumn('orders', 'manual_status') ? $order->manual_status : null,
            'order_date' => optional($order->order_date)?->toDateString(),
            'client_id' => $order->customer_id,
            'client_snapshot' => $order->relationLoaded('client') && $order->client !== null
                ? [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'inn' => $order->client->inn,
                    'type' => $order->client->type,
                ]
                : null,
            'own_company_id' => $order->own_company_id,
            'own_company_bank_account_id' => Schema::hasColumn('orders', 'own_company_bank_account_id')
                ? $order->own_company_bank_account_id
                : null,
            'responsible_id' => $order->manager_id,
            'responsible_name' => $order->relationLoaded('manager') ? $order->manager?->name : null,
            'payment_terms' => $order->payment_terms,
            'special_notes' => $order->special_notes,
            'basic_terms' => $this->serializeBasicTermsForWizard($order),
            'can_promote_basic_terms' => $this->canPromoteBasicTerms($request),
            'can_direct_promote_basic_terms' => $this->canDirectPromoteBasicTerms($request),
            'svh_name' => $order->svh_name,
            'svh_address' => Schema::hasColumn('orders', 'svh_address') ? $order->svh_address : null,
            'customs_post_code' => Schema::hasColumn('orders', 'customs_post_code') ? $order->customs_post_code : null,
            'cargo_declared_sum' => Schema::hasColumn('orders', 'cargo_declared_sum') ? $order->cargo_declared_sum : null,
            'customs_declaration_place' => Schema::hasColumn('orders', 'customs_declaration_place') ? $order->customs_declaration_place : null,
            'customs_commodity_code' => Schema::hasColumn('orders', 'customs_commodity_code') ? $order->customs_commodity_code : null,
            'is_international_transport' => Schema::hasColumn('orders', 'is_international_transport')
                ? $order->isInternationalTransportEffective()
                : (bool) data_get($wizardState, 'is_international_transport', false),
            'loading_types' => $this->resolveLoadingTypesForOrder($order),
            'additional_expenses' => Schema::hasColumn('orders', 'additional_expenses') ? $order->additional_expenses : null,
            'additional_expenses_payment_date' => Schema::hasColumn('orders', 'additional_expenses_payment_date')
                ? optional($order->additional_expenses_payment_date)?->toDateString()
                : null,
            'insurance' => Schema::hasColumn('orders', 'insurance') ? $order->insurance : null,
            'bonus' => Schema::hasColumn('orders', 'bonus') ? $order->bonus : null,
            'performers' => $performers,
            'route_points' => $routePoints,
            'cargo_items' => $this->hydrateCargoItemsForWizard(
                $this->mergeCargoItemsWithWizardState(
                    $cargoItems->map(fn ($cargo): array => [
                        'id' => $cargo->id,
                        'name' => $cargo->ati_cargo_name ?: $cargo->title,
                        'description' => $cargo->description,
                        'weight_value' => $cargo->weight_value ?? $cargo->weight,
                        'weight_kg' => $cargo->weight_value ?? $cargo->weight,
                        'weight_unit' => $cargo->weight_unit ?: 'kg',
                        'volume_m3' => $cargo->volume,
                        'length_m' => $cargo->length,
                        'width_m' => $cargo->width,
                        'height_m' => $cargo->height,
                        'diameter_m' => $cargo->diameter,
                        'package_type' => $cargo->packing_type,
                        'pack_type_id' => $cargo->pack_type_id,
                        'pack_type_label' => $cargo->pack_type_label,
                        'loading_type_id' => $cargo->loading_type_id,
                        'loading_type_code' => $cargo->loading_type_code,
                        'loading_type_label' => $cargo->loading_type_label,
                        'loading_type_items' => $cargo->loading_type_items ?? $this->dictionaryItemsFromFlatCargo($cargo, 'loading_type'),
                        'truck_body_type_id' => $cargo->truck_body_type_id,
                        'truck_body_type_code' => $cargo->truck_body_type_code,
                        'truck_body_type_label' => $cargo->truck_body_type_label,
                        'truck_body_type_items' => $cargo->truck_body_type_items ?? $this->dictionaryItemsFromFlatCargo($cargo, 'truck_body_type'),
                        'trailer_type_id' => $cargo->trailer_type_id,
                        'trailer_type_code' => $cargo->trailer_type_code,
                        'trailer_type_label' => $cargo->trailer_type_label,
                        'trailer_type_items' => $cargo->trailer_type_items ?? $this->dictionaryItemsFromFlatCargo($cargo, 'trailer_type'),
                        'package_count' => $cargo->package_count ?? $cargo->pallet_count,
                        'dangerous_goods' => $cargo->is_hazardous,
                        'dangerous_class' => $cargo->hazard_class,
                        'hs_code' => $cargo->hs_code,
                        'cargo_type' => $cargo->cargo_type ?: 'general',
                        'cargo_type_id' => $cargo->cargo_type_id,
                        'cargo_type_label' => $cargo->cargo_type_label,
                        'is_oversized' => $cargo->is_oversized,
                        'is_fragile' => $cargo->is_fragile,
                        'ati_cargo_payload' => $this->atiCargoPayloadForWizard($cargo->ati_cargo_payload),
                        'performer_allocations' => $this->performerAllocationsFromCargoPayload($cargo->ati_cargo_payload),
                    ])->values()->all(),
                    $useWizardState && is_array($wizardState) ? ($wizardState['cargo_items'] ?? []) : [],
                ),
                $performers,
            ),
            'financial_term' => [
                'client_price' => $useWizardState
                    ? $this->resolveClientPriceForWizardPayload($wizardFt, $order, $financialTerm)
                    : ($order->customer_rate !== null
                        ? $order->customer_rate
                        : $financialTerm?->client_price),
                'client_currency' => $useWizardState
                    ? ($wizardFt['client_currency'] ?? 'RUB')
                    : ($financialTerm?->client_currency ?? 'RUB'),
                'client_payment_form' => $this->normalizePaymentFormCodeForWizard(
                    $useWizardState
                        ? ($wizardFt['client_payment_form'] ?? $order->customer_payment_form)
                        : $order->customer_payment_form,
                    PaymentFormDictionary::defaultClientVatCode(),
                ),
                'client_request_mode' => data_get($paymentTermsConfig, 'client.request_mode', 'single_request'),
                'client_payment_schedule' => $paymentTermsConfig['client']['payment_schedule'] ?? [],
                'client_payment_terms' => $useWizardState
                    ? $this->resolveClientPaymentTermsForWizardPayload($wizardFt, $financialTerm, $order)
                    : (string) ($financialTerm?->client_payment_terms ?? $order->customer_payment_term ?? ''),
                'contractors_costs' => $contractorsCosts,
                'additional_costs' => $additionalCosts,
                // Источник истины — пересчёт в orders.kpi_percent; снимок wizard_state отстаёт после inline/grid.
                'kpi_percent' => $order->kpi_percent ?? ($useWizardState ? ($wizardFt['kpi_percent'] ?? 0) : 0),
                'client_norms_penalties' => $useWizardState
                    ? (is_array($wizardFt['client_norms_penalties'] ?? null) ? $wizardFt['client_norms_penalties'] : [])
                    : [],
                'carrier_norms_by_leg' => $useWizardState
                    ? (is_array($wizardFt['carrier_norms_by_leg'] ?? null) ? $wizardFt['carrier_norms_by_leg'] : [])
                    : [],
            ],
            'payment_settlement' => app(PaymentSettlementSummaryBuilder::class)->forOrder($order),
            'print_form_template_selection' => is_array($wizardState) && is_array($wizardState['print_form_template_selection'] ?? null)
                ? $wizardState['print_form_template_selection']
                : [],
            'documents' => $documents->map(fn (OrderDocument $document): array => $this->serializeOrderDocument(
                $document,
                $order,
                $canManageOrderDocuments,
                $canApproveOrderDocuments,
                $templatesById
            ))->values()->all(),
            'status_logs' => $statusLogs->map(fn ($log): array => [
                'id' => $log->id,
                'status_from' => $log->status_from,
                'status_to' => $log->status_to,
                'comment' => $log->comment,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderDocument(
        OrderDocument $document,
        Order $order,
        bool $canManageOrderDocuments,
        bool $canApproveOrderDocuments,
        Collection $templatesById,
    ): array {
        $base = [
            'id' => $document->id,
            'type' => $document->type,
            'flow' => data_get($document->metadata, 'flow', 'uploaded'),
            'party' => $this->resolveWizardDocumentParty($document, $templatesById),
            'stage' => data_get($document->metadata, 'stage'),
            'order_leg_stage' => data_get($document->metadata, 'order_leg_stage')
                ?? data_get($document->metadata, 'stage'),
            'carrier_contractor_id' => data_get($document->metadata, 'carrier_contractor_id'),
            'carrier_slot' => data_get($document->metadata, 'carrier_slot'),
            'contractor_id' => data_get($document->metadata, 'contractor_id'),
            'requirement_slot_key' => data_get($document->metadata, 'requirement_slot_key'),
            'route_legs_as_table_rows' => (bool) data_get($document->metadata, 'route_legs_as_table_rows', false),
            'requirement_key' => data_get($document->metadata, 'requirement_key'),
            'number' => $document->number,
            'document_date' => optional($document->document_date)?->toDateString(),
            'status' => $document->status,
            'original_name' => $document->original_name,
            'file_path' => $document->file_path,
            'generated_pdf_path' => $document->generated_pdf_path,
            'template_id' => $document->template_id,
            'is_print_workflow' => false,
        ];

        $isPrintWorkflow = (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template')
            || (data_get($document->metadata, 'flow') === 'print_template_workflow');

        if (! $isPrintWorkflow) {
            $uploadedPreviewUrl = filled($document->file_path)
                ? route('orders.documents.preview-uploaded', [$order, $document])
                : null;

            return array_merge($base, [
                'uploaded_file_preview_url' => $uploadedPreviewUrl,
            ]);
        }

        $workflowStatus = Schema::hasColumn('order_documents', 'workflow_status')
            ? $document->workflow_status
            : null;

        $requiresCounterpartySignature = $this->orderDocumentRequiresCounterpartySignature($document);

        $signatureStatus = Schema::hasColumn('order_documents', 'signature_status')
            ? $document->signature_status
            : null;

        $draftUrl = filled($document->file_path)
            ? route('orders.documents.download-draft', [$order, $document])
            : null;

        $draftPreviewUrl = $draftUrl !== null
            ? route('orders.documents.preview-draft', [$order, $document])
            : null;

        if (in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true) && blank($document->generated_pdf_path)) {
            app(OrderPrintDocumentWorkflowService::class)->ensureApprovedWorkflowPdf($document);
            $document->refresh();
        }

        $finalUrl = filled($document->generated_pdf_path)
            ? route('orders.documents.download-final', [$order, $document])
            : null;

        $isFinalized = $workflowStatus === OrderDocumentWorkflowStatus::FINALIZED;
        $isWorkflowSigned = in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true);

        $printPartyLabel = null;
        $printTemplateName = $this->printTemplateName($document, $templatesById);
        $printTemplateCode = $this->printTemplateCode($document, $templatesById);
        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $tpl */
            $tpl = $templatesById->get($document->template_id);
            $printPartyLabel = $this->printTemplatePartyLabel($tpl);
        }

        return array_merge($base, [
            'is_print_workflow' => true,
            'source' => Schema::hasColumn('order_documents', 'source') ? $document->source : null,
            'workflow_status' => $workflowStatus,
            'workflow_status_label' => $workflowStatus ? OrderDocumentWorkflowStatus::label($workflowStatus) : null,
            'print_party_label' => $printPartyLabel,
            'print_template_name' => $printTemplateName,
            'print_template_code' => $printTemplateCode,
            'approval_requested_at' => Schema::hasColumn('order_documents', 'approval_requested_at')
                ? optional($document->approval_requested_at)?->toIso8601String()
                : null,
            'approved_at' => Schema::hasColumn('order_documents', 'approved_at')
                ? optional($document->approved_at)?->toIso8601String()
                : null,
            'rejected_at' => Schema::hasColumn('order_documents', 'rejected_at')
                ? optional($document->rejected_at)?->toIso8601String()
                : null,
            'rejection_reason' => Schema::hasColumn('order_documents', 'rejection_reason')
                ? $document->rejection_reason
                : null,
            'draft_download_url' => $isWorkflowSigned ? null : $draftUrl,
            'draft_preview_url' => $isWorkflowSigned ? null : $draftPreviewUrl,
            'final_pdf_download_url' => $finalUrl,
            'final_pdf_storage_path' => filled($document->generated_pdf_path) ? $document->generated_pdf_path : null,
            'draft_storage_path' => filled($document->file_path) ? $document->file_path : null,
            'can_request_approval' => $canManageOrderDocuments && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::DRAFT,
                OrderDocumentWorkflowStatus::REJECTED,
            ], true),
            'can_regenerate_draft' => $canManageOrderDocuments && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::DRAFT,
                OrderDocumentWorkflowStatus::REJECTED,
            ], true),
            'can_approve' => $canApproveOrderDocuments && $workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'can_reject' => $canApproveOrderDocuments && $workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'can_finalize' => $canManageOrderDocuments && $workflowStatus === OrderDocumentWorkflowStatus::APPROVED,
            'can_discard_print_draft' => $this->canDiscardPrintWorkflowDocument(
                $document,
                $workflowStatus,
                $canManageOrderDocuments,
                $canApproveOrderDocuments
            ),
            'requires_counterparty_signature' => $requiresCounterpartySignature,
            'signature_status' => $signatureStatus,
            'signature_status_label' => $this->orderDocumentSignatureStatusLabel($signatureStatus),
            'signature_followup_hint' => $this->orderDocumentSignatureFollowupHint(
                $workflowStatus,
                $signatureStatus,
                $requiresCounterpartySignature
            ),
        ]);
    }

    private function orderDocumentRequiresCounterpartySignature(OrderDocument $document): bool
    {
        if (Schema::hasColumn('order_documents', 'requires_counterparty_signature')) {
            return (bool) ($document->requires_counterparty_signature ?? false);
        }

        if ($document->template_id === null) {
            return false;
        }

        $template = PrintFormTemplate::query()->find($document->template_id);

        return (bool) ($template?->requires_counterparty_signature ?? false);
    }

    private function isEmptyPrintWorkflowArtifact(OrderDocument $document): bool
    {
        $isPrintWorkflow = (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template')
            || data_get($document->metadata, 'flow') === 'print_template_workflow';

        return $isPrintWorkflow
            && blank($document->file_path)
            && blank($document->generated_pdf_path)
            && blank($document->original_name);
    }

    private function canDiscardPrintWorkflowDocument(
        OrderDocument $document,
        ?string $workflowStatus,
        bool $canManageOrderDocuments,
        bool $canApproveOrderDocuments,
    ): bool {
        if ($workflowStatus === OrderDocumentWorkflowStatus::FINALIZED) {
            return false;
        }

        $signatureStatus = Schema::hasColumn('order_documents', 'signature_status')
            ? (string) ($document->signature_status ?? '')
            : '';

        if ($signatureStatus === 'signed_both_sides') {
            return false;
        }

        $managerMayDiscardBeforeApproval = $canManageOrderDocuments
            && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::DRAFT,
                OrderDocumentWorkflowStatus::REJECTED,
            ], true);

        $signerMayDiscardUntilBothSignatures = $canApproveOrderDocuments
            && in_array($workflowStatus, [
                OrderDocumentWorkflowStatus::PENDING_APPROVAL,
                OrderDocumentWorkflowStatus::APPROVED,
            ], true);

        return $managerMayDiscardBeforeApproval || $signerMayDiscardUntilBothSignatures;
    }

    private function printTemplatePartyLabel(PrintFormTemplate $template): string
    {
        return match ((string) $template->party) {
            'customer' => 'Заказчик',
            'carrier' => 'Перевозчик',
            'internal' => 'Внутренняя',
            default => (string) $template->party,
        };
    }

    /**
     * @param  Collection<int, PrintFormTemplate>  $templatesById
     */
    private function printTemplateName(OrderDocument $document, Collection $templatesById): ?string
    {
        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $template */
            $template = $templatesById->get($document->template_id);

            return $template->name;
        }

        $name = data_get($document->metadata, 'template_name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /**
     * @param  Collection<int, PrintFormTemplate>  $templatesById
     */
    private function printTemplateCode(OrderDocument $document, Collection $templatesById): ?string
    {
        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $template */
            $template = $templatesById->get($document->template_id);

            return $template->code;
        }

        $code = data_get($document->metadata, 'template_code');

        return is_string($code) && trim($code) !== '' ? trim($code) : null;
    }

    /**
     * Подпись в смысле «документ подписан сторонами», не путать с workflow_status печатной заявки.
     */
    private function orderDocumentSignatureStatusLabel(?string $signatureStatus): ?string
    {
        if ($signatureStatus === null || $signatureStatus === '') {
            return null;
        }

        return match ($signatureStatus) {
            'not_requested' => 'Подпись не зафиксирована',
            'pending_signature' => 'Ожидается подпись',
            'signed_internal' => 'Подписано у нас (внутренняя)',
            'signed_both_sides' => 'Подписано с обеих сторон',
            default => $signatureStatus,
        };
    }

    private function orderDocumentSignatureFollowupHint(
        ?string $workflowStatus,
        ?string $signatureStatus,
        bool $requiresCounterpartySignature,
    ): ?string {
        if (! $requiresCounterpartySignature) {
            return null;
        }

        if ($workflowStatus !== OrderDocumentWorkflowStatus::FINALIZED) {
            return null;
        }

        if ($signatureStatus === 'signed_both_sides') {
            return null;
        }

        if ($signatureStatus === 'signed_internal') {
            return 'Нужна подпись клиента: приложите скан (или отдельный файл в блоке «Документы заказчика» ниже).';
        }

        return null;
    }

    /**
     * Приводит форму оплаты к кодам валидации мастера (vat / no_vat / cash), т.к. в БД и старых снимках могли быть подписи («с НДС» и т.п.).
     *
     * @param  'vat'|'no_vat'|'cash'  $default
     */
    private function normalizePaymentFormCodeForWizard(?string $value, string $default = 'no_vat'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $trimmed = trim($value);
        $allowed = PaymentFormDictionary::allowedCodesForValidation();
        if (in_array($trimmed, $allowed, true)) {
            $normalized = PaymentFormDictionary::normalizeForStorage($trimmed);

            return $normalized ?? $default;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (str_contains($lower, 'без') && str_contains($lower, 'ндс')) {
            return 'no_vat';
        }

        if (str_contains($lower, 'нал')) {
            return 'cash';
        }

        if (str_contains($lower, 'ндс')) {
            return PaymentFormDictionary::defaultClientVatCode();
        }

        return $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveWizardState(Order $order): ?array
    {
        if (! Schema::hasColumn('orders', 'wizard_state')) {
            return null;
        }

        $payload = $order->wizard_state;
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $serializedPerformers
     * @return list<array<string, mixed>>
     */
    private function mergePerformersFromWizardState(array $serializedPerformers, mixed $wizardPerformers): array
    {
        if (! is_array($wizardPerformers) || $wizardPerformers === []) {
            return $serializedPerformers;
        }

        $wizardByStage = collect($wizardPerformers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => $this->normalizeStageIdentifierForWizard((string) ($row['stage'] ?? 'leg_1')));

        if ($serializedPerformers === []) {
            return $this->performersPayloadWithContractorLabels(
                $wizardByStage->values()->map(fn (array $row): array => $this->normalizePerformerRowFromWizardState($row))->all()
            );
        }

        return $this->performersPayloadWithContractorLabels(
            collect($serializedPerformers)
                ->map(function (array $serialized) use ($wizardByStage): array {
                    $stageKey = $this->normalizeStageIdentifierForWizard((string) ($serialized['stage'] ?? 'leg_1'));
                    $wizardRow = $wizardByStage->get($stageKey);

                    if (! is_array($wizardRow)) {
                        return $serialized;
                    }

                    return $this->mergePerformerRow($serialized, $this->normalizePerformerRowFromWizardState($wizardRow));
                })
                ->values()
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizePerformerRowFromWizardState(array $row): array
    {
        $normalized = [
            'stage' => $this->normalizeStageIdentifierForWizard(isset($row['stage']) ? (string) $row['stage'] : null),
            'carrier_mode' => ($row['carrier_mode'] ?? 'single') === 'split' ? 'split' : 'single',
            'contractor_id' => isset($row['contractor_id']) && $row['contractor_id'] !== null && $row['contractor_id'] !== ''
                ? (int) $row['contractor_id']
                : null,
            'contractor_name' => isset($row['contractor_name']) ? (string) $row['contractor_name'] : null,
            'fleet_vehicle_id' => isset($row['fleet_vehicle_id']) && $row['fleet_vehicle_id'] !== null && $row['fleet_vehicle_id'] !== ''
                ? (int) $row['fleet_vehicle_id']
                : null,
            'fleet_driver_id' => isset($row['fleet_driver_id']) && $row['fleet_driver_id'] !== null && $row['fleet_driver_id'] !== ''
                ? (int) $row['fleet_driver_id']
                : null,
            'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode(isset($row['execution_mode']) ? (string) $row['execution_mode'] : null)
                ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                : null,
            'fleet_trip_id' => isset($row['fleet_trip_id']) && $row['fleet_trip_id'] !== null && $row['fleet_trip_id'] !== ''
                ? (int) $row['fleet_trip_id']
                : null,
            'loading_actual' => PerformerRouteActualDates::normalizeDate($row['loading_actual'] ?? null),
            'unloading_actual' => PerformerRouteActualDates::normalizeDate($row['unloading_actual'] ?? null),
            'carrier_portal_submission' => is_array($row['carrier_portal_submission'] ?? null)
                ? $row['carrier_portal_submission']
                : null,
            'split_carriers' => [],
        ];

        if ($normalized['carrier_mode'] === 'split' && is_array($row['split_carriers'] ?? null)) {
            $normalized['split_carriers'] = collect($row['split_carriers'])
                ->filter(fn (mixed $slot): bool => is_array($slot))
                ->values()
                ->map(function (array $slot, int $index): array {
                    return [
                        'slot' => (int) ($slot['slot'] ?? ($index + 1)),
                        'contractor_id' => isset($slot['contractor_id']) && $slot['contractor_id'] !== null && $slot['contractor_id'] !== ''
                            ? (int) $slot['contractor_id']
                            : null,
                        'contractor_name' => isset($slot['contractor_name']) ? (string) $slot['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null && $slot['fleet_vehicle_id'] !== ''
                            ? (int) $slot['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null && $slot['fleet_driver_id'] !== ''
                            ? (int) $slot['fleet_driver_id']
                            : null,
                        'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode(isset($slot['execution_mode']) ? (string) $slot['execution_mode'] : null)
                            ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                            : null,
                        'fleet_trip_id' => isset($slot['fleet_trip_id']) && $slot['fleet_trip_id'] !== null && $slot['fleet_trip_id'] !== ''
                            ? (int) $slot['fleet_trip_id']
                            : null,
                        'carrier_portal_submission' => is_array($slot['carrier_portal_submission'] ?? null)
                            ? $slot['carrier_portal_submission']
                            : null,
                        'loading_actual' => PerformerRouteActualDates::normalizeDate($slot['loading_actual'] ?? null),
                        'unloading_actual' => PerformerRouteActualDates::normalizeDate($slot['unloading_actual'] ?? null),
                    ];
                })
                ->all();
            $normalized['loading_actual'] = null;
            $normalized['unloading_actual'] = null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $wizard
     * @return array<string, mixed>
     */
    private function mergePerformerRow(array $base, array $wizard): array
    {
        if (($wizard['carrier_mode'] ?? 'single') === 'split') {
            $baseSlots = is_array($base['split_carriers'] ?? null) ? $base['split_carriers'] : [];
            $wizardSlots = is_array($wizard['split_carriers'] ?? null) ? $wizard['split_carriers'] : [];

            return [
                ...$base,
                'carrier_mode' => 'split',
                'split_carriers' => $wizardSlots === [] && $baseSlots !== []
                    ? $baseSlots
                    : $this->mergeSplitCarriersFromWizardState($baseSlots, $wizardSlots),
                'contractor_id' => null,
                'contractor_name' => null,
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
                'loading_actual' => null,
                'unloading_actual' => null,
            ];
        }

        return [
            ...$base,
            'carrier_mode' => 'single',
            'split_carriers' => [],
            'contractor_id' => $wizard['contractor_id'] ?? $base['contractor_id'] ?? null,
            'contractor_name' => $wizard['contractor_name'] ?? $base['contractor_name'] ?? null,
            'fleet_vehicle_id' => $wizard['fleet_vehicle_id'] ?? $base['fleet_vehicle_id'] ?? null,
            'fleet_driver_id' => $wizard['fleet_driver_id'] ?? $base['fleet_driver_id'] ?? null,
            'execution_mode' => $wizard['execution_mode'] ?? $base['execution_mode'] ?? null,
            'fleet_trip_id' => $wizard['fleet_trip_id'] ?? $base['fleet_trip_id'] ?? null,
            'carrier_portal_submission' => $wizard['carrier_portal_submission'] ?? $base['carrier_portal_submission'] ?? null,
            'loading_actual' => $wizard['loading_actual'] ?? $base['loading_actual'] ?? null,
            'unloading_actual' => $wizard['unloading_actual'] ?? $base['unloading_actual'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, LegContractorAssignment>  $assignments
     * @param  array<string, mixed>  $metadataPerformer
     * @return list<array<string, mixed>>
     */
    private function splitCarriersFromAssignmentsAndMetadata($assignments, array $metadataPerformer): array
    {
        $metadataSlots = collect(is_array($metadataPerformer['split_carriers'] ?? null) ? $metadataPerformer['split_carriers'] : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['slot'] ?? 1));

        if ($assignments->isNotEmpty()) {
            return $assignments
                ->sortBy('carrier_slot')
                ->map(function ($assignment) use ($metadataSlots): array {
                    $slot = (int) ($assignment->carrier_slot ?? 1);
                    $meta = $metadataSlots->get($slot, []);

                    return [
                        'slot' => $slot,
                        'contractor_id' => $assignment->contractor_id !== null ? (int) $assignment->contractor_id : null,
                        'contractor_name' => filled($meta['contractor_name'] ?? null) ? (string) $meta['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($meta['fleet_vehicle_id']) && $meta['fleet_vehicle_id'] !== null && $meta['fleet_vehicle_id'] !== ''
                            ? (int) $meta['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($meta['fleet_driver_id']) && $meta['fleet_driver_id'] !== null && $meta['fleet_driver_id'] !== ''
                            ? (int) $meta['fleet_driver_id']
                            : null,
                    ];
                })
                ->values()
                ->all();
        }

        if ($metadataSlots->isNotEmpty()) {
            return $metadataSlots
                ->sortKeys()
                ->map(fn (array $meta, int $slot): array => [
                    'slot' => $slot,
                    'contractor_id' => isset($meta['contractor_id']) && $meta['contractor_id'] !== null && $meta['contractor_id'] !== ''
                        ? (int) $meta['contractor_id']
                        : null,
                    'contractor_name' => filled($meta['contractor_name'] ?? null) ? (string) $meta['contractor_name'] : null,
                    'fleet_vehicle_id' => isset($meta['fleet_vehicle_id']) && $meta['fleet_vehicle_id'] !== null && $meta['fleet_vehicle_id'] !== ''
                        ? (int) $meta['fleet_vehicle_id']
                        : null,
                    'fleet_driver_id' => isset($meta['fleet_driver_id']) && $meta['fleet_driver_id'] !== null && $meta['fleet_driver_id'] !== ''
                        ? (int) $meta['fleet_driver_id']
                        : null,
                ])
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * Слоты из wizard_state не должны затирать перевозчиков, уже восстановленных из БД.
     *
     * @param  list<array<string, mixed>>  $baseSlots
     * @param  list<array<string, mixed>>  $wizardSlots
     * @return list<array<string, mixed>>
     */
    private function mergeSplitCarriersFromWizardState(array $baseSlots, array $wizardSlots): array
    {
        if ($wizardSlots === []) {
            return $baseSlots;
        }

        $baseBySlot = collect($baseSlots)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['slot'] ?? 1));

        return collect($wizardSlots)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values()
            ->map(function (array $wizardSlot) use ($baseBySlot): array {
                $slot = (int) ($wizardSlot['slot'] ?? 1);
                $baseSlot = $baseBySlot->get($slot);
                $wizardContractorId = isset($wizardSlot['contractor_id']) && $wizardSlot['contractor_id'] !== null && $wizardSlot['contractor_id'] !== ''
                    ? (int) $wizardSlot['contractor_id']
                    : null;
                $baseContractorId = is_array($baseSlot) && isset($baseSlot['contractor_id']) && $baseSlot['contractor_id'] !== null && $baseSlot['contractor_id'] !== ''
                    ? (int) $baseSlot['contractor_id']
                    : null;

                return [
                    'slot' => $slot,
                    'contractor_id' => $wizardContractorId ?? $baseContractorId,
                    'contractor_name' => filled($wizardSlot['contractor_name'] ?? null)
                        ? (string) $wizardSlot['contractor_name']
                        : (is_array($baseSlot) ? ($baseSlot['contractor_name'] ?? null) : null),
                    'fleet_vehicle_id' => isset($wizardSlot['fleet_vehicle_id']) && $wizardSlot['fleet_vehicle_id'] !== null && $wizardSlot['fleet_vehicle_id'] !== ''
                        ? (int) $wizardSlot['fleet_vehicle_id']
                        : (is_array($baseSlot) && isset($baseSlot['fleet_vehicle_id']) ? $baseSlot['fleet_vehicle_id'] : null),
                    'fleet_driver_id' => isset($wizardSlot['fleet_driver_id']) && $wizardSlot['fleet_driver_id'] !== null && $wizardSlot['fleet_driver_id'] !== ''
                        ? (int) $wizardSlot['fleet_driver_id']
                        : (is_array($baseSlot) && isset($baseSlot['fleet_driver_id']) ? $baseSlot['fleet_driver_id'] : null),
                    'carrier_portal_submission' => is_array($wizardSlot['carrier_portal_submission'] ?? null)
                        ? $wizardSlot['carrier_portal_submission']
                        : (is_array($baseSlot['carrier_portal_submission'] ?? null) ? $baseSlot['carrier_portal_submission'] : null),
                    'loading_actual' => PerformerRouteActualDates::normalizeDate(
                        $wizardSlot['loading_actual'] ?? (is_array($baseSlot) ? ($baseSlot['loading_actual'] ?? null) : null),
                    ),
                    'unloading_actual' => PerformerRouteActualDates::normalizeDate(
                        $wizardSlot['unloading_actual'] ?? (is_array($baseSlot) ? ($baseSlot['unloading_actual'] ?? null) : null),
                    ),
                ];
            })
            ->all();
    }

    /**
     * Должен совпадать с {@see OrderWizardService} для сопоставления этапов.
     */
    private function normalizeStageIdentifierForWizard(?string $stage): string
    {
        return CargoPerformerAllocationNormalizer::normalizeStageIdentifier($stage);
    }

    /**
     * @return array<string, mixed>
     */
    private function atiCargoPayloadForWizard(mixed $payload): array
    {
        if (! is_array($payload) || $payload === [] || array_is_list($payload)) {
            return [];
        }

        return $payload;
    }

    /**
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    private function performerAllocationsFromCargoPayload(mixed $payload): array
    {
        $normalizedPayload = $this->atiCargoPayloadForWizard($payload);
        $raw = $normalizedPayload['performer_allocations'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        return CargoPerformerAllocationNormalizer::normalizeForStorage($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $cargoItems
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function hydrateCargoItemsForWizard(array $cargoItems, array $performers): array
    {
        $performerRows = array_values(array_filter($performers, static fn (mixed $row): bool => is_array($row)));

        return collect($cargoItems)
            ->map(fn (array $cargo): array => OrderCargoItemsPayloadNormalizer::normalizeCargoItem($cargo, $performerRows))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $cargoItems
     * @return list<array<string, mixed>>
     */
    private function mergeCargoItemsWithWizardState(array $cargoItems, mixed $wizardCargoItems): array
    {
        if (! is_array($wizardCargoItems) || $wizardCargoItems === []) {
            return $cargoItems;
        }

        $wizardByName = collect($wizardCargoItems)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => mb_strtolower(trim((string) ($row['name'] ?? ''))));

        return collect($cargoItems)
            ->map(function (array $cargo) use ($wizardByName): array {
                $dbAllocations = is_array($cargo['performer_allocations'] ?? null)
                    ? $cargo['performer_allocations']
                    : [];
                if ($dbAllocations !== []) {
                    $cargo['performer_allocations'] = CargoPerformerAllocationNormalizer::normalizeForStorage($dbAllocations);
                    $atiPayload = is_array($cargo['ati_cargo_payload'] ?? null) ? $cargo['ati_cargo_payload'] : [];
                    $atiPayload['performer_allocations'] = $cargo['performer_allocations'];
                    $cargo['ati_cargo_payload'] = $atiPayload;

                    return $cargo;
                }

                $key = mb_strtolower(trim((string) ($cargo['name'] ?? '')));
                $wizardRow = $wizardByName->get($key);
                if (! is_array($wizardRow)) {
                    return $cargo;
                }

                $wizardAllocations = is_array($wizardRow['performer_allocations'] ?? null)
                    ? CargoPerformerAllocationNormalizer::normalizeForStorage($wizardRow['performer_allocations'])
                    : [];
                if ($wizardAllocations === []) {
                    return $cargo;
                }

                $cargo['performer_allocations'] = $wizardAllocations;
                $atiPayload = is_array($cargo['ati_cargo_payload'] ?? null) ? $cargo['ati_cargo_payload'] : [];
                $atiPayload['performer_allocations'] = $wizardAllocations;
                $cargo['ati_cargo_payload'] = $atiPayload;

                return $cargo;
            })
            ->values()
            ->all();
    }

    /**
     * Исполнители для мастера: плечи заказа; перевозчик — из назначения на плече, при отсутствии — из snapshot `financial_terms.contractors_costs`.
     *
     * @return list<array{stage: string|null, contractor_id: int|null, contractor_name: string|null}>
     */
    private function serializePerformersPayload(Order $order, ?FinancialTerm $financialTerm): array
    {
        $costRows = $financialTerm?->contractors_costs ?? [];
        if (! is_array($costRows)) {
            $costRows = [];
        }
        $savedPerformers = is_array($order->performers) ? $order->performers : [];
        $savedPerformersByStage = collect($savedPerformers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => $this->normalizeStageIdentifierForWizard((string) ($row['stage'] ?? 'leg_1')));

        $costsByNormalizedStage = collect($costRows)
            ->keyBy(fn (array $cost): string => $this->contractorCostSnapshotKey($cost));

        if (Schema::hasTable('order_legs')) {
            if (Schema::hasTable('leg_contractor_assignments')) {
                $order->loadMissing(['legs.contractorAssignments', 'legs.contractorAssignment']);
            } else {
                $order->loadMissing(['legs']);
            }
        }

        $fromLegs = $order->relationLoaded('legs')
            ? $order->legs
                ->sortBy('sequence')
                ->values()
                ->map(function ($leg, int $index) use ($costsByNormalizedStage, $order): array {
                    $normalized = $this->normalizeStageIdentifierForWizard((string) ($leg->description ?? 'leg_1'));
                    $contractorId = null;
                    $metadataPerformer = is_array($leg->metadata ?? null)
                        ? (is_array($leg->metadata['performer'] ?? null) ? $leg->metadata['performer'] : [])
                        : [];
                    $carrierMode = (string) ($metadataPerformer['carrier_mode'] ?? 'single');
                    $splitCarriers = is_array($metadataPerformer['split_carriers'] ?? null)
                        ? $metadataPerformer['split_carriers']
                        : [];

                    if (Schema::hasTable('leg_contractor_assignments') && $leg->relationLoaded('contractorAssignments')) {
                        $assignments = $leg->contractorAssignments;
                        if ($assignments->count() > 1 || $carrierMode === 'split') {
                            $carrierMode = 'split';
                            $splitCarriers = $this->splitCarriersFromAssignmentsAndMetadata($assignments, $metadataPerformer);
                        } elseif ($assignments->count() === 1) {
                            $contractorId = $assignments->first()?->contractor_id;
                        }
                    } elseif (Schema::hasTable('leg_contractor_assignments')) {
                        $contractorId = $leg->contractorAssignment?->contractor_id;
                    }

                    if ($contractorId === null && $carrierMode !== 'split') {
                        $fromCost = $costsByNormalizedStage->get($this->contractorCostSnapshotKey([
                            'stage' => $normalized,
                            'carrier_slot' => null,
                        ]));
                        $contractorId = is_array($fromCost) ? ($fromCost['contractor_id'] ?? null) : null;
                    }

                    if ($contractorId === null && $index === 0 && $order->carrier_id !== null && $carrierMode !== 'split') {
                        $contractorId = $order->carrier_id;
                    }

                    return [
                        'stage' => $normalized,
                        'carrier_mode' => $carrierMode,
                        'contractor_id' => $carrierMode === 'split' ? null : ($contractorId !== null ? (int) $contractorId : null),
                        'contractor_name' => isset($metadataPerformer['contractor_name']) ? (string) $metadataPerformer['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($metadataPerformer['fleet_vehicle_id']) && $metadataPerformer['fleet_vehicle_id'] !== null
                            ? (int) $metadataPerformer['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($metadataPerformer['fleet_driver_id']) && $metadataPerformer['fleet_driver_id'] !== null
                            ? (int) $metadataPerformer['fleet_driver_id']
                            : null,
                        'carrier_portal_submission' => is_array($metadataPerformer['carrier_portal_submission'] ?? null)
                            ? $metadataPerformer['carrier_portal_submission']
                            : null,
                        'split_carriers' => $splitCarriers,
                    ];
                })
                ->all()
            : [];

        if ($fromLegs !== []) {
            return $this->performersPayloadWithFleetLabels(
                $this->performersPayloadWithContractorLabels(
                    $this->mergeSavedPerformersIntoLegPayload($fromLegs, $savedPerformers)
                ),
            );
        }

        if ($costRows !== []) {
            $fromCosts = collect($costRows)
                ->map(function (array $cost) use ($savedPerformersByStage): array {
                    $stage = (string) ($cost['stage'] ?? 'leg_1');
                    $saved = $savedPerformersByStage->get($this->normalizeStageIdentifierForWizard($stage));

                    return [
                        'stage' => $this->normalizeStageIdentifierForWizard($stage),
                        'contractor_id' => isset($cost['contractor_id']) && $cost['contractor_id'] !== null ? (int) $cost['contractor_id'] : null,
                        'fleet_vehicle_id' => isset($saved['fleet_vehicle_id']) && $saved['fleet_vehicle_id'] !== null ? (int) $saved['fleet_vehicle_id'] : null,
                        'fleet_driver_id' => isset($saved['fleet_driver_id']) && $saved['fleet_driver_id'] !== null ? (int) $saved['fleet_driver_id'] : null,
                        'carrier_portal_submission' => is_array($saved['carrier_portal_submission'] ?? null)
                            ? $saved['carrier_portal_submission']
                            : null,
                    ];
                })
                ->values()
                ->all();

            return $this->performersPayloadWithFleetLabels(
                $this->performersPayloadWithContractorLabels($fromCosts),
            );
        }

        if ($order->carrier_id !== null) {
            return $this->performersPayloadWithFleetLabels(
                $this->performersPayloadWithContractorLabels([
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => (int) $order->carrier_id,
                    ],
                ]),
            );
        }

        return [];
    }

    /**
     * Подпись перевозчика в мастере: поле поиска не должно пустеть, если id есть, а контрагент не попал в укороченный список props.
     *
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function performersPayloadWithContractorLabels(array $performers): array
    {
        if ($performers === []) {
            return [];
        }

        $ids = collect($performers)
            ->flatMap(function (array $performer): array {
                if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    return collect($performer['split_carriers'])
                        ->filter(fn (mixed $slot): bool => is_array($slot))
                        ->map(fn (array $slot): ?int => isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                            ? (int) $slot['contractor_id']
                            : null)
                        ->all();
                }

                $id = $performer['contractor_id'] ?? null;

                return $id !== null && $id !== '' ? [(int) $id] : [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect($performers)
                ->map(fn (array $p): array => [...$p, 'contractor_name' => null])
                ->all();
        }

        $names = Contractor::query()->whereIn('id', $ids)->pluck('name', 'id');

        return collect($performers)
            ->map(function (array $p) use ($names): array {
                if (($p['carrier_mode'] ?? 'single') === 'split' && is_array($p['split_carriers'] ?? null)) {
                    $p['split_carriers'] = collect($p['split_carriers'])
                        ->map(function (array $slot) use ($names): array {
                            $idInt = isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                                ? (int) $slot['contractor_id']
                                : null;
                            $label = $idInt !== null ? $names->get($idInt) : null;
                            $slotName = trim((string) ($slot['contractor_name'] ?? ''));

                            return [
                                ...$slot,
                                'contractor_name' => $slotName !== ''
                                    ? $slotName
                                    : ($label !== null && $label !== '' ? (string) $label : null),
                            ];
                        })
                        ->all();

                    return $p;
                }

                $id = $p['contractor_id'] ?? null;
                $idInt = $id !== null && $id !== '' ? (int) $id : null;
                $label = $idInt !== null ? $names->get($idInt) : null;
                $existingName = trim((string) ($p['contractor_name'] ?? ''));

                return [
                    ...$p,
                    'contractor_name' => $existingName !== ''
                        ? $existingName
                        : ($label !== null && $label !== '' ? (string) $label : null),
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function performersPayloadWithFleetLabels(array $performers): array
    {
        if ($performers === []) {
            return [];
        }

        $vehicleIds = [];
        $driverIds = [];

        foreach ($performers as $performer) {
            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    if (isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null) {
                        $vehicleIds[(int) $slot['fleet_vehicle_id']] = true;
                    }

                    if (isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null) {
                        $driverIds[(int) $slot['fleet_driver_id']] = true;
                    }
                }

                continue;
            }

            if (isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null) {
                $vehicleIds[(int) $performer['fleet_vehicle_id']] = true;
            }

            if (isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null) {
                $driverIds[(int) $performer['fleet_driver_id']] = true;
            }
        }

        $vehicleLabels = $this->fleetVehicleLabels(array_keys($vehicleIds));
        $driverLabels = $this->fleetDriverLabels(array_keys($driverIds));

        return collect($performers)
            ->map(function (array $performer) use ($vehicleLabels, $driverLabels): array {
                if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    $performer['split_carriers'] = collect($performer['split_carriers'])
                        ->map(function (array $slot) use ($vehicleLabels, $driverLabels): array {
                            $vehicleId = isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null
                                ? (int) $slot['fleet_vehicle_id']
                                : null;
                            $driverId = isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null
                                ? (int) $slot['fleet_driver_id']
                                : null;

                            return [
                                ...$slot,
                                'fleet_vehicle_label' => $vehicleId !== null ? ($vehicleLabels[$vehicleId] ?? null) : null,
                                'fleet_driver_label' => $driverId !== null ? ($driverLabels[$driverId] ?? null) : null,
                            ];
                        })
                        ->all();

                    return $performer;
                }

                $vehicleId = isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null
                    ? (int) $performer['fleet_vehicle_id']
                    : null;
                $driverId = isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null
                    ? (int) $performer['fleet_driver_id']
                    : null;

                return [
                    ...$performer,
                    'fleet_vehicle_label' => $vehicleId !== null ? ($vehicleLabels[$vehicleId] ?? null) : null,
                    'fleet_driver_label' => $driverId !== null ? ($driverLabels[$driverId] ?? null) : null,
                ];
            })
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function fleetVehicleLabels(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_vehicles')) {
            return [];
        }

        return FleetVehicle::query()
            ->whereIn('id', $ids)
            ->get(['id', 'tractor_plate', 'trailer_plate', 'tractor_brand'])
            ->mapWithKeys(function (FleetVehicle $vehicle): array {
                $parts = array_filter([
                    $vehicle->tractor_plate,
                    $vehicle->trailer_plate,
                    $vehicle->tractor_brand,
                ]);

                $label = $parts !== [] ? implode(' · ', $parts) : 'ТС #'.$vehicle->id;

                return [$vehicle->id => $label];
            })
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function fleetDriverLabels(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_drivers')) {
            return [];
        }

        return FleetDriver::query()
            ->whereIn('id', $ids)
            ->get(['id', 'full_name', 'phone'])
            ->mapWithKeys(function (FleetDriver $driver): array {
                $label = trim((string) $driver->full_name);
                if ($driver->phone) {
                    $label .= ' · '.$driver->phone;
                }

                return [$driver->id => $label !== '' ? $label : 'Водитель #'.$driver->id];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $fromLegs
     * @param  list<array<string, mixed>>  $savedPerformers
     * @return list<array<string, mixed>>
     */
    private function mergeSavedPerformersIntoLegPayload(array $fromLegs, array $savedPerformers): array
    {
        if ($savedPerformers === []) {
            return $fromLegs;
        }

        $savedByStage = collect($savedPerformers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => $this->normalizeStageIdentifierForWizard((string) ($row['stage'] ?? 'leg_1')));

        return collect($fromLegs)
            ->map(function (array $legRow) use ($savedByStage): array {
                $saved = $savedByStage->get($this->normalizeStageIdentifierForWizard((string) ($legRow['stage'] ?? 'leg_1')));

                if (! is_array($saved)) {
                    return $legRow;
                }

                return $this->mergePerformerRow($legRow, $this->normalizePerformerRowFromWizardState($saved));
            })
            ->values()
            ->all();
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

    private function loadOrderForEditing(Order $order): Order
    {
        $relations = ['client', 'ownCompany', 'manager', 'legs.routePoints'];

        if (Schema::hasTable('leg_contractor_assignments')) {
            $relations[] = 'legs.contractorAssignments';
            $relations[] = 'legs.contractorAssignment';
        }

        if (Schema::hasTable('leg_costs')) {
            $relations[] = 'legs.cost';
        }

        if (Schema::hasColumn('cargos', 'order_id')) {
            $relations[] = 'cargoItems';
        }

        if (Schema::hasTable('order_documents')) {
            $relations[] = 'documents';
        }

        if (Schema::hasTable('financial_terms')) {
            $relations[] = 'financialTerms';
        }

        if (Schema::hasTable('order_status_logs')) {
            $relations[] = 'statusLogs';
        }

        return $order->load($relations);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function printFormTemplateCatalog(): Collection
    {
        if (! Schema::hasTable('print_form_templates')) {
            return collect();
        }

        /** @var PrintFormTemplateOrderEligibility $eligibility */
        $eligibility = app(PrintFormTemplateOrderEligibility::class);

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
            ->map(fn (PrintFormTemplate $template): array => $eligibility->templateToArray($template))
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

        /** @var PrintFormTemplateOrderEligibility $eligibility */
        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $ownCompanyId = $order?->own_company_id !== null ? (int) $order->own_company_id : null;
        $isInternational = $order !== null
            ? $order->isInternationalTransportEffective()
            : false;
        $contractorIds = $order !== null ? $eligibility->contractorIdsForOrder($order) : [];

        return $this->printFormTemplateCatalog()
            ->filter(fn (array $template): bool => $eligibility->isArrayTemplateAvailableForContext(
                $template,
                $ownCompanyId,
                $isInternational,
                $party,
                $contractorIds,
            ))
            ->sortByDesc(fn (array $template): int => $eligibility->specificityScore(
                $template,
                $ownCompanyId,
                $isInternational,
            ))
            ->values();
    }

    private function isTemplateAvailableForOrder(
        PrintFormTemplate $template,
        Order $order,
        ?string $party = null,
        ?bool $isInternationalTransport = null,
    ): bool {
        /** @var PrintFormTemplateOrderEligibility $eligibility */
        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        return $eligibility->isTemplateAvailableForOrder($template, $order, $party, $isInternationalTransport);
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
     * @return Collection<int, Cargo>
     */
    private function orderCargoItems(Order $order): Collection
    {
        if (Schema::hasColumn('cargos', 'order_id')) {
            return $order->cargoItems;
        }

        return Cargo::query()
            ->select('cargos.*')
            ->join('cargo_leg', 'cargo_leg.cargo_id', '=', 'cargos.id')
            ->join('order_legs', 'order_legs.id', '=', 'cargo_leg.order_leg_id')
            ->where('order_legs.order_id', $order->id)
            ->orderBy('cargos.id')
            ->get();
    }

    /**
     * @return list<array{id:int|null, code:string|null, label:string|null}>
     */
    private function dictionaryItemsFromFlatCargo(Cargo $cargo, string $prefix): array
    {
        $item = [
            'id' => $cargo->{$prefix.'_id'} ?? null,
            'code' => $cargo->{$prefix.'_code'} ?? null,
            'label' => $cargo->{$prefix.'_label'} ?? null,
        ];

        return $item['id'] !== null || $item['code'] !== null || $item['label'] !== null ? [$item] : [];
    }

    /**
     * @param  list<array{value:int, code:string, label:string}>  $fallback
     * @return list<array{value:int, code:string|null, label:string, ati_id:int|null}>
     */
    private function atiDictionaryOptions(string $dictionary, array $fallback): array
    {
        if (! Schema::hasTable('ati_dictionary_items')) {
            return array_map(
                fn (array $item): array => [
                    'value' => $item['value'],
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'ati_id' => $item['value'],
                ],
                $fallback
            );
        }

        $items = AtiDictionaryItem::query()
            ->where('dictionary', $dictionary)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'ati_id', 'code', 'label']);

        if ($items->isEmpty()) {
            return array_map(
                fn (array $item): array => [
                    'value' => $item['value'],
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'ati_id' => $item['value'],
                ],
                $fallback
            );
        }

        return $items
            ->map(fn (AtiDictionaryItem $item): array => [
                'value' => $item->ati_id ?? $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'ati_id' => $item->ati_id,
            ])
            ->values()
            ->all();
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
    private function fallbackPackageTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'pallet', 'label' => 'Паллета'],
            ['value' => 2, 'code' => 'box', 'label' => 'Короб'],
            ['value' => 3, 'code' => 'crate', 'label' => 'Ящик'],
            ['value' => 4, 'code' => 'roll', 'label' => 'Рулон'],
            ['value' => 5, 'code' => 'bag', 'label' => 'Мешок'],
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
     * @return list<string>
     */
    private function resolveLoadingTypesForOrder(Order $order): array
    {
        $types = collect($order->legs ?? [])
            ->flatMap(fn ($leg) => $leg->routePoints ?? [])
            ->filter(fn ($point): bool => ($point->type ?? null) === 'loading')
            ->flatMap(function ($point): array {
                $fromMetadata = data_get($point->metadata, 'loading_types', []);
                if (is_array($fromMetadata)) {
                    return $fromMetadata;
                }

                $fromNormalized = data_get($point->normalized_data, 'loading_types', []);

                return is_array($fromNormalized) ? $fromNormalized : [];
            })
            ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
            ->filter()
            ->unique()
            ->values();

        if ($types->isEmpty()) {
            $fallback = data_get($order->metadata, 'loading_types', []);
            if (is_array($fallback)) {
                $types = collect($fallback)
                    ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
                    ->filter()
                    ->unique()
                    ->values();
            }
        }

        if ($types->isEmpty()) {
            $fallback = data_get($order->wizard_state, 'loading_types', []);
            if (is_array($fallback)) {
                $types = collect($fallback)
                    ->map(fn (mixed $value): ?string => $this->normalizeLoadingType($value))
                    ->filter()
                    ->unique()
                    ->values();
            }
        }

        return $types->all();
    }

    private function normalizeLoadingType(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            'top', 'верх' => 'top',
            'side', 'бок' => 'side',
            'rear', 'зад' => 'rear',
            'full', 'растентовка', 'полная растентовка' => 'full',
            'tail_lift', 'hydraulic', 'гидроборт' => 'tail_lift',
            'crane', 'manipulator', 'манипулятор' => 'crane',
            default => null,
        };
    }

    /**
     * Объединяет строки contractors_costs из БД со снимком мастера: суммы и условия из financial_terms сохраняются,
     * если в wizard_state они не заданы (частичный снимок не должен обнулять ставки перевозчика).
     *
     * @param  list<array<string, mixed>>  $dbCosts
     * @param  list<array<string, mixed>>  $wizardCosts
     * @return list<array<string, mixed>>
     */
    private function contractorCostSnapshotKey(array $row): string
    {
        $stage = $this->normalizeStageIdentifierForWizard((string) ($row['stage'] ?? 'leg_1'));
        $slot = isset($row['carrier_slot']) && $row['carrier_slot'] !== null && $row['carrier_slot'] !== ''
            ? (int) $row['carrier_slot']
            : 0;

        return "{$stage}#{$slot}";
    }

    private function mergeContractorsCostsSnapshots(array $dbCosts, array $wizardCosts): array
    {
        $byKey = [];

        foreach ($dbCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $byKey[$this->contractorCostSnapshotKey($row)] = $row;
        }

        foreach ($wizardCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $this->contractorCostSnapshotKey($row);
            $base = $byKey[$key] ?? [];
            $merged = array_merge($base, $row);

            if (! array_key_exists('contractor_id', $row) || $row['contractor_id'] === null || $row['contractor_id'] === '') {
                $merged['contractor_id'] = $base['contractor_id'] ?? null;
            }

            if (! array_key_exists('amount', $row) || $row['amount'] === null || $row['amount'] === '') {
                $merged['amount'] = $base['amount'] ?? null;
            }

            if (! array_key_exists('currency', $row) || $row['currency'] === null || $row['currency'] === '') {
                $merged['currency'] = $base['currency'] ?? 'RUB';
            }

            if (! array_key_exists('payment_form', $row) || $row['payment_form'] === null || $row['payment_form'] === '') {
                $merged['payment_form'] = $base['payment_form'] ?? null;
            }

            if (! array_key_exists('payment_schedule', $row) || $row['payment_schedule'] === null
                || (is_array($row['payment_schedule']) && $row['payment_schedule'] === [])) {
                $schedule = $base['payment_schedule'] ?? [];
                $merged['payment_schedule'] = is_array($schedule) ? $schedule : [];
            } elseif ($this->shouldPreserveBaseContractorCostPaymentSchedule(
                $row['payment_schedule'] ?? null,
                $base['payment_schedule'] ?? null,
            )) {
                $merged['payment_schedule'] = $base['payment_schedule'];
            }

            if (! array_key_exists('payment_terms', $row) || trim((string) ($row['payment_terms'] ?? '')) === '') {
                $merged['payment_terms'] = $base['payment_terms'] ?? '';
            }

            $merged['stage'] = $row['stage'] ?? $base['stage'] ?? null;
            $byKey[$key] = $merged;
        }

        return array_values($byKey);
    }

    /**
     * @param  list<array<string, mixed>>  $dbCosts
     * @param  list<array<string, mixed>>  $wizardCosts
     * @return list<array<string, mixed>>
     */
    private function mergeAdditionalCostsSnapshots(array $dbCosts, array $wizardCosts): array
    {
        $byId = [];

        foreach ($dbCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['id'] ?? ''));
            if ($key === '') {
                continue;
            }

            $byId[$key] = $row;
        }

        foreach ($wizardCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['id'] ?? ''));
            if ($key === '') {
                continue;
            }

            $base = $byId[$key] ?? [];
            $merged = array_merge($base, $row);

            foreach (['contractor_id', 'contractor_name', 'service_date', 'amount', 'currency', 'payment_form', 'payment_terms'] as $field) {
                if (! array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                    if (array_key_exists($field, $base)) {
                        $merged[$field] = $base[$field];
                    }
                }
            }

            if (! array_key_exists('payment_schedule', $row)
                || $row['payment_schedule'] === null
                || (is_array($row['payment_schedule']) && $row['payment_schedule'] === [])) {
                $schedule = $base['payment_schedule'] ?? [];
                $merged['payment_schedule'] = is_array($schedule) ? $schedule : [];
            }

            $byId[$key] = $merged;
        }

        if ($wizardCosts === [] && $dbCosts !== []) {
            return array_values($byId);
        }

        if ($wizardCosts !== [] && $byId === []) {
            return array_values(array_filter($wizardCosts, static fn (mixed $row): bool => is_array($row)));
        }

        $ordered = [];

        foreach ($wizardCosts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['id'] ?? ''));
            if ($key !== '' && isset($byId[$key])) {
                $ordered[] = $byId[$key];

                continue;
            }

            $ordered[] = $row;
        }

        foreach ($byId as $key => $row) {
            $alreadyIncluded = collect($ordered)->contains(
                fn (array $existing): bool => trim((string) ($existing['id'] ?? '')) === $key,
            );

            if (! $alreadyIncluded) {
                $ordered[] = $row;
            }
        }

        return $ordered;
    }

    private function shouldPreserveBaseContractorCostPaymentSchedule(mixed $wizardSchedule, mixed $baseSchedule): bool
    {
        if (! is_array($baseSchedule) || $baseSchedule === []) {
            return false;
        }

        if (! is_array($wizardSchedule) || $wizardSchedule === []) {
            return true;
        }

        $baseInstallments = isset($baseSchedule['installments']) && is_array($baseSchedule['installments'])
            ? $baseSchedule['installments']
            : [];
        $wizardInstallments = isset($wizardSchedule['installments']) && is_array($wizardSchedule['installments'])
            ? $wizardSchedule['installments']
            : [];

        if ($baseInstallments !== [] && $wizardInstallments === []) {
            return true;
        }

        if ($baseInstallments === [] && $wizardInstallments === []) {
            $baseDays = (int) ($baseSchedule['postpayment_days'] ?? 0);
            $wizardDays = (int) ($wizardSchedule['postpayment_days'] ?? 0);

            return $baseDays > 0 && $wizardDays === 0;
        }

        return false;
    }

    /**
     * @param  list<array{stage: string|null, contractor_id: int|null}>  $serializedPerformers
     * @return list<array<string, mixed>>
     */
    private function normalizeContractorsCosts(Order $order, ?FinancialTerm $financialTerm, array $serializedPerformers = []): array
    {
        $savedCosts = $financialTerm?->contractors_costs ?? [];

        if (! is_array($savedCosts)) {
            $savedCosts = [];
        }

        if ($savedCosts !== []) {
            $contractorsCosts = collect($savedCosts)
                ->filter(fn (mixed $cost): bool => is_array($cost))
                ->values()
                ->map(function (array $cost) use ($financialTerm, $order): array {
                    $normalized = [
                        'stage' => $cost['stage'] ?? 'leg_1',
                        'carrier_slot' => isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                            ? (int) $cost['carrier_slot']
                            : null,
                        'contractor_id' => isset($cost['contractor_id']) && $cost['contractor_id'] !== null && $cost['contractor_id'] !== ''
                            ? (int) $cost['contractor_id']
                            : null,
                        'amount' => $cost['amount'] ?? null,
                        'currency' => $cost['currency'] ?? $financialTerm?->client_currency ?? 'RUB',
                        'payment_form' => $cost['payment_form'] ?? $order->carrier_payment_form ?? 'no_vat',
                        'payment_schedule' => is_array($cost['payment_schedule'] ?? null) ? $cost['payment_schedule'] : [],
                        'payment_terms' => $cost['payment_terms'] ?? '',
                        'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode($cost['execution_mode'] ?? null)
                            ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                            : null,
                        'is_additional' => ! empty($cost['is_additional']),
                        'incurred_date' => filled($cost['incurred_date'] ?? null)
                            ? substr((string) $cost['incurred_date'], 0, 10)
                            : null,
                    ];

                    if ($normalized['is_additional'] && ! ContractorCostRowClassification::isAdditionalStage((string) $normalized['stage'])) {
                        $normalized['stage'] = 'additional';
                    }

                    return $normalized;
                })
                ->all();

            $additionalIndex = 1;
            foreach ($contractorsCosts as &$costRow) {
                if (empty($costRow['is_additional'])) {
                    continue;
                }

                if (! ContractorCostRowClassification::isAdditionalStage((string) ($costRow['stage'] ?? ''))) {
                    $costRow['stage'] = "additional_{$additionalIndex}";
                    $additionalIndex++;
                }
            }
            unset($costRow);

            return $this->mergeOrderCarrierRateIntoContractorsCosts($contractorsCosts, $order->carrier_rate);
        }

        $contractorsCosts = collect($serializedPerformers)
            ->values()
            ->flatMap(function ($performer, int $index) use ($financialTerm, $order): array {
                if (! is_array($performer)) {
                    return [[
                        'stage' => 'leg_'.($index + 1),
                        'carrier_slot' => null,
                        'contractor_id' => null,
                        'amount' => null,
                        'currency' => $financialTerm?->client_currency ?? 'RUB',
                        'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                        'payment_schedule' => [],
                        'payment_terms' => '',
                    ]];
                }

                if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    return collect($performer['split_carriers'])
                        ->filter(fn (mixed $slot): bool => is_array($slot))
                        ->map(fn (array $slot): array => [
                            'stage' => $performer['stage'] ?? 'leg_1',
                            'carrier_slot' => (int) ($slot['slot'] ?? 1),
                            'contractor_id' => isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                                ? (int) $slot['contractor_id']
                                : null,
                            'amount' => null,
                            'currency' => $financialTerm?->client_currency ?? 'RUB',
                            'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                            'payment_schedule' => [],
                            'payment_terms' => '',
                        ])
                        ->all();
                }

                return [[
                    'stage' => $performer['stage'] ?? 'leg_'.($index + 1),
                    'carrier_slot' => null,
                    'contractor_id' => $performer['contractor_id'] ?? null,
                    'amount' => null,
                    'currency' => $financialTerm?->client_currency ?? 'RUB',
                    'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                    'payment_schedule' => [],
                    'payment_terms' => '',
                ]];
            })
            ->all();

        return $this->mergeOrderCarrierRateIntoContractorsCosts($contractorsCosts, $order->carrier_rate);
    }

    /**
     * Цена заказчика: нулевой или пустой снимок wizard_state не должен затирать {@see Order::$customer_rate}.
     *
     * @param  array<string, mixed>  $wizardFt
     */
    private function resolveClientPriceForWizardPayload(array $wizardFt, Order $order, ?FinancialTerm $financialTerm): ?float
    {
        if (array_key_exists('client_price', $wizardFt)) {
            $fromWizard = $wizardFt['client_price'];
            if ($fromWizard !== null && $fromWizard !== '' && is_numeric($fromWizard) && (float) $fromWizard > 0) {
                return round((float) $fromWizard, 2);
            }
        }

        if ($order->customer_rate !== null && is_numeric($order->customer_rate) && (float) $order->customer_rate > 0) {
            return round((float) $order->customer_rate, 2);
        }

        if ($financialTerm?->client_price !== null && is_numeric($financialTerm->client_price) && (float) $financialTerm->client_price > 0) {
            return round((float) $financialTerm->client_price, 2);
        }

        return null;
    }

    /**
     * Текст сводки для мастера: пустая строка в {@see Order::$wizard_state} не должна перекрывать
     * сохранённые {@see FinancialTerm::$client_payment_terms} и поле {@see Order::$customer_payment_term}.
     *
     * @param  array<string, mixed>  $wizardFt
     */
    private function resolveClientPaymentTermsForWizardPayload(
        array $wizardFt,
        ?FinancialTerm $financialTerm,
        Order $order,
    ): string {
        $fromFt = trim((string) ($financialTerm?->client_payment_terms ?? ''));
        if ($fromFt !== '') {
            return $fromFt;
        }

        $fromWizard = trim((string) ($wizardFt['client_payment_terms'] ?? ''));
        if ($fromWizard !== '') {
            return $fromWizard;
        }

        return trim((string) ($order->customer_payment_term ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePaymentTermsConfig(?string $paymentTerms): array
    {
        if (blank($paymentTerms)) {
            return [];
        }

        try {
            $decoded = json_decode($paymentTerms, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Таблица заказов хранит итоговые ставки в `orders`; карточка подгружает детализацию из `financial_terms`.
     * После inline-редактирования ставок в гриде синхронизируем строку финансовых условий, чтобы не расходились данные.
     */
    private function syncFinancialTermsFromOrderRates(Order $order): void
    {
        if (! Schema::hasTable('financial_terms')) {
            return;
        }

        $orderId = (int) $order->getKey();
        if ($orderId <= 0) {
            return;
        }

        $financialTerm = FinancialTerm::query()->where('order_id', $orderId)->first();

        if ($financialTerm === null) {
            $serializedPerformers = $this->serializePerformersPayload($order, null);
            $attributes = [
                'order_id' => $orderId,
                'client_price' => $order->customer_rate,
                'client_currency' => 'RUB',
                'contractors_costs' => $this->normalizeContractorsCosts($order, null, $serializedPerformers),
                'total_cost' => 0,
                'margin' => 0,
                'additional_costs' => [],
            ];

            if (Schema::hasColumn('financial_terms', 'client_payment_terms')) {
                $attributes['client_payment_terms'] = $order->customer_payment_term;
            }

            $financialTerm = $order->financialTerms()->create(
                collect($attributes)->except('order_id')->all(),
            );
        }

        if ($order->customer_rate !== null) {
            $financialTerm->client_price = $order->customer_rate;
        }

        $serializedPerformers = $this->serializePerformersPayload($order, $financialTerm);
        $costs = $this->normalizeContractorsCosts($order, $financialTerm, $serializedPerformers);
        $costs = $this->applyOrderCarrierPaymentFormToSyncedCosts($order, $costs);
        $financialTerm->contractors_costs = $costs;

        $contractorsSum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));
        $additionalTotal = collect($financialTerm->additional_costs ?? [])
            ->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
        $financialTerm->total_cost = $contractorsSum + $additionalTotal;

        $kpiPercent = (float) ($order->kpi_percent ?? 0);
        $clientPrice = (float) ($order->customer_rate ?? $financialTerm->client_price ?? 0);
        $cashToCash = CashToCashMarginCalculator::isCashToCash(
            (string) ($order->customer_payment_form ?? ''),
            $costs,
        );
        $financialTerm->margin = CashToCashMarginCalculator::margin(
            $clientPrice,
            (float) $financialTerm->total_cost,
            $kpiPercent,
            $cashToCash,
        );

        $order->refresh();

        $mergedPaymentTerms = $this->mergeOrderPaymentTermsCarriersIntoJson($order, $costs);
        if (Schema::hasColumn('financial_terms', 'payment_terms_snapshot') && $mergedPaymentTerms !== null) {
            $financialTerm->payment_terms_snapshot = $mergedPaymentTerms;
        }

        $financialTerm->save();

        $fill = [];
        if (Schema::hasColumn('orders', 'carrier_payment_term')) {
            $term = CarrierPaymentTermResolver::fromContractorsCostsArray($costs);
            if ($term !== null) {
                $fill['carrier_payment_term'] = $term;
            }
        }
        if ($mergedPaymentTerms !== null && Schema::hasColumn('orders', 'payment_terms')) {
            $fill['payment_terms'] = $mergedPaymentTerms;
        }
        if ($fill !== []) {
            $order->forceFill($fill)->saveQuietly();
        }
    }

    /**
     * Обновляет блок `carriers` в JSON `orders.payment_terms`, сохраняя `client` при наличии.
     *
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    private function mergeOrderPaymentTermsCarriersIntoJson(Order $order, array $contractorsCosts): ?string
    {
        if (! Schema::hasColumn('orders', 'payment_terms')) {
            return null;
        }

        try {
            $config = OrderPaymentTermsConfigResolver::forSync($order);

            if (! isset($config['client']) || ! is_array($config['client'])) {
                $config['client'] = [
                    'payment_form' => $order->customer_payment_form,
                    'request_mode' => 'single_request',
                    'payment_schedule' => [],
                ];
            }

            $config['carriers'] = collect($contractorsCosts)
                ->map(function (array $c): array {
                    $schedule = $c['payment_schedule'] ?? [];
                    if (! is_array($schedule)) {
                        $schedule = [];
                    }

                    return [
                        'stage' => $c['stage'] ?? null,
                        'contractor_id' => isset($c['contractor_id']) && $c['contractor_id'] !== null ? (int) $c['contractor_id'] : null,
                        'payment_form' => $c['payment_form'] ?? null,
                        'payment_schedule' => $schedule,
                    ];
                })
                ->values()
                ->all();

            return json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * После инлайна в гриде `orders.carrier_payment_form` — источник правды для одной строки затрат (одно плечо).
     *
     * @param  list<array<string, mixed>>  $costs
     * @return list<array<string, mixed>>
     */
    private function applyOrderCarrierPaymentFormToSyncedCosts(Order $order, array $costs): array
    {
        $form = $order->carrier_payment_form;
        if ($form === null || $form === '' || $form === 'mixed') {
            return $costs;
        }

        if (count($costs) !== 1) {
            return $costs;
        }

        $costs[0]['payment_form'] = $form;

        return $costs;
    }

    /**
     * @param  list<array<string, mixed>>  $costs
     * @return list<array<string, mixed>>
     */
    private function mergeOrderCarrierRateIntoContractorsCosts(array $costs, mixed $carrierRate): array
    {
        if ($carrierRate === null || count($costs) === 0) {
            return $costs;
        }

        $legIndexes = [];
        foreach ($costs as $index => $cost) {
            if (! ContractorCostRowClassification::isAdditional($cost)) {
                $legIndexes[] = $index;
            }
        }

        if ($legIndexes === []) {
            return $costs;
        }

        $sum = collect($legIndexes)
            ->sum(fn (int $index): float => (float) ($costs[$index]['amount'] ?? 0));

        if (abs(round((float) $carrierRate, 2) - round($sum, 2)) < 0.01) {
            return $costs;
        }

        if (count($legIndexes) === 1) {
            $costs[$legIndexes[0]]['amount'] = (float) $carrierRate;

            return $costs;
        }

        $firstLegIndex = $legIndexes[0];
        $rest = collect($legIndexes)
            ->slice(1)
            ->sum(fn (int $index): float => (float) ($costs[$index]['amount'] ?? 0));
        $costs[$firstLegIndex]['amount'] = max(0, (float) $carrierRate - $rest);

        return $costs;
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
     * Получить ID контрагентов, связанных с заказом
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

        // Также можно добавить контрагентов из финансовых условий
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
     * @return array{driver: string, label: string}
     */
    private function printWorkflowDocumentStorageMeta(): array
    {
        $documentStorage = app(DocumentStorageService::class);
        $driver = $documentStorage->configuredDriver();
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

    private function resolveWizardDocumentParty(OrderDocument $document, Collection $templatesById): string
    {
        $party = (string) data_get($document->metadata, 'party', 'internal');

        if (in_array($party, ['customer', 'carrier'], true)) {
            return $party;
        }

        if ($document->template_id !== null && $templatesById->has($document->template_id)) {
            /** @var PrintFormTemplate $template */
            $template = $templatesById->get($document->template_id);
            $templateParty = (string) ($template->party ?? '');

            if (in_array($templateParty, ['customer', 'carrier'], true)) {
                return $templateParty;
            }
        }

        if (data_get($document->metadata, 'carrier_contractor_id')) {
            return 'carrier';
        }

        $code = strtolower((string) data_get($document->metadata, 'template_code', ''));

        if (str_contains($code, 'perevoz') || str_contains($code, 'carrier') || str_contains($code, 'перевоз')) {
            return 'carrier';
        }

        if (str_contains($code, 'zak') || str_contains($code, 'kl') || str_contains($code, 'client') || str_contains($code, 'зак')) {
            return 'customer';
        }

        if (in_array($document->type, ['request', 'contract_request'], true)) {
            return 'customer';
        }

        return 'internal';
    }

    private function userCanUseOrderAsTemplate(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if (RoleAccess::isAdminUser($user)) {
            return true;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

        return $scope === 'all' || (int) $order->manager_id === (int) $user->id;
    }
}
