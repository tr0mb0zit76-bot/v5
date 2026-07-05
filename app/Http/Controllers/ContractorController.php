<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmContractorRiskAssessmentRequest;
use App\Http\Requests\StoreContractorRequest;
use App\Http\Requests\UpdateContractorRequest;
use App\Models\Contractor;
use App\Models\ContractorActivityType;
use App\Models\ContractorDocument;
use App\Models\ContractorRiskAssessment;
use App\Models\User;
use App\Services\Checko\ContractorRiskAssessmentService;
use App\Services\Checko\ContractorScoringService;
use App\Services\Contractor\ContractorInsightDraftService;
use App\Services\Contractor\ContractorLimitApprovalService;
use App\Services\Contractor\ContractorPortraitService;
use App\Services\ContractorCreditService;
use App\Services\ContractorDocumentSyncService;
use App\Services\ContractorOperationalStatusService;
use App\Services\ContractorPartnerCardService;
use App\Services\DaDataService;
use App\Services\DocumentStorageService;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Services\PrintForm\ContractorPrintFormProfileResolver;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\ContractorDuplicateGuard;
use App\Support\ContractorPortraitDictionary;
use App\Support\ContractorTableColumns;
use App\Support\ContractorWorkStatus;
use App\Support\CurrencyDictionary;
use App\Support\EdoProviderDictionary;
use App\Support\MailSync\MailContractorAllowlist;
use App\Support\OwnFleetCatalog;
use App\Support\PartyNormsPenalties;
use App\Support\PaymentFormDictionary;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContractorController extends Controller
{
    public function __construct(
        private readonly ContractorDocumentSyncService $contractorDocumentSync,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function index(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function create(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function store(StoreContractorRequest $request): RedirectResponse
    {
        $contractor = DB::transaction(function () use ($request): Contractor {
            $validated = $request->validated();

            $attributes = $this->extractContractorAttributes($validated);
            $attributes['owner_id'] = $validated['owner_id'] ?? $request->user()?->id;
            $attributes['created_by'] = $request->user()?->id;
            $attributes['updated_by'] = $request->user()?->id;

            $contractor = Contractor::query()->create($attributes);

            $this->syncNestedData($contractor, $validated, $request->user()?->id);

            return $contractor;
        });

        MailContractorAllowlist::forgetCache();

        return to_route('contractors.show', [
            'contractor' => $contractor,
            ...$this->listContext($request),
        ]);
    }

    public function show(Request $request, Contractor $contractor): Response
    {
        return $this->renderPage($request, $contractor);
    }

    public function edit(Request $request, Contractor $contractor): Response
    {
        return $this->renderPage($request, $contractor);
    }

    public function update(UpdateContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        DB::transaction(function () use ($request, $contractor): void {
            $validated = $request->validated();

            $contractor->update([
                ...$this->extractContractorAttributes($validated, $contractor),
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($contractor, $validated, $request->user()?->id);
        });

        $contractor->refresh();

        MailContractorAllowlist::forgetCache();

        return to_route('contractors.show', [
            'contractor' => $contractor,
            ...$this->listContext($request),
        ]);
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        abort_if(
            $this->contractorHasOrders($contractor),
            422,
            'Нельзя удалить контрагента, связанного с заказами.'
        );

        $contractor->delete();

        MailContractorAllowlist::forgetCache();

        return to_route('contractors.index');
    }

    public function suggestParty(Request $request, DaDataService $daDataService): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'suggestions' => $daDataService->suggestParty($request->string('query')->toString()),
        ]);
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inn' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:255'],
            'ignore_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            ContractorDuplicateGuard::checkPayload(
                isset($validated['inn']) ? (string) $validated['inn'] : null,
                isset($validated['name']) ? (string) $validated['name'] : null,
                $request->user(),
                isset($validated['ignore_id']) ? (int) $validated['ignore_id'] : null,
            ),
        );
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

    public function suggestBank(Request $request, DaDataService $daDataService): JsonResponse
    {
        $request->validate([
            'bik' => ['required', 'string', 'size:9'],
        ]);

        return response()->json([
            'suggestions' => $daDataService->suggestBank($request->string('bik')->toString()),
        ]);
    }

    public function storeActivityType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(Schema::hasTable('contractor_activity_types'), 422, 'Справочник видов деятельности недоступен.');

        $normalizedName = trim($validated['name']);

        $activityType = ContractorActivityType::query()->firstOrCreate([
            'name' => $normalizedName,
        ]);

        return response()->json([
            'activityType' => [
                'id' => $activityType->id,
                'name' => $activityType->name,
            ],
        ], 201);
    }

    public function massUpdateOwner(Request $request): JsonResponse
    {
        abort_unless(Schema::hasColumn('contractors', 'owner_id'), 404);

        $ownerIdRules = ['nullable', 'integer'];
        $ownerIdRules[] = Schema::hasColumn('users', 'is_active')
            ? Rule::exists('users', 'id')->where('is_active', true)
            : 'exists:users,id';

        $validated = $request->validate([
            'contractor_ids' => ['required', 'array', 'min:1'],
            'contractor_ids.*' => ['integer', 'distinct', 'exists:contractors,id'],
            'owner_id' => $ownerIdRules,
        ]);

        $contractorIds = collect($validated['contractor_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $ownerId = $validated['owner_id'] ?? null;
        $type = trim((string) $request->query('type', ''));

        $visibleContractorIds = Contractor::query()
            ->visibleTo($request->user(), $type)
            ->whereIn('id', $contractorIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        abort_if($visibleContractorIds === [], 403);

        $updatedCount = Contractor::query()
            ->whereIn('id', $visibleContractorIds)
            ->update([
                'owner_id' => $ownerId,
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);

        $ownerName = $ownerId === null
            ? ''
            : (string) User::query()->whereKey($ownerId)->value('name');

        return response()->json([
            'message' => 'Владелец успешно обновлён для '.$updatedCount.' контрагентов.',
            'contractor_ids' => $visibleContractorIds,
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            'updated_count' => $updatedCount,
        ]);
    }

    public function scoring(
        Request $request,
        Contractor $contractor,
        ContractorScoringService $scoringService,
        ContractorOperationalStatusService $statusService,
    ): JsonResponse {
        if ($contractor->isOwnCompanyProfile()) {
            return response()->json([
                'ok' => false,
                'message' => 'Скоринг и проверка не применяются к своей компании.',
            ]);
        }

        $refresh = $request->boolean('refresh');

        if ($refresh) {
            $statusService->sync($contractor);
        }

        $payload = $scoringService->buildPayload($contractor, $refresh);

        return response()->json($payload);
    }

    public function confirmRiskAssessment(
        ConfirmContractorRiskAssessmentRequest $request,
        Contractor $contractor,
        ContractorRiskAssessmentService $assessmentService,
    ): JsonResponse {
        if ($contractor->isOwnCompanyProfile()) {
            return response()->json([
                'ok' => false,
                'message' => 'Скоринг и проверка не применяются к своей компании.',
            ], 422);
        }

        $validated = $request->validated();
        $assessment = ContractorRiskAssessment::query()->findOrFail($validated['assessment_id']);

        $outcome = (string) $validated['outcome'];
        $scheduleTarget = (string) ($validated['schedule_target'] ?? 'customer');

        $result = $assessmentService->confirm(
            $contractor,
            $assessment,
            $request->user(),
            $outcome,
            (float) ($validated['applied_debt_limit'] ?? 0),
            (int) ($validated['applied_postpayment_days'] ?? 0),
            $scheduleTarget,
        );

        return response()->json([
            'ok' => true,
            'assessment_id' => $result['assessment']->id,
            'outcome' => $result['assessment']->outcome,
            'verification' => $result['verification'],
        ]);
    }

    public function requestLimitApproval(
        Request $request,
        Contractor $contractor,
        ContractorLimitApprovalService $approvalService,
    ): JsonResponse {
        if ($contractor->isOwnCompanyProfile()) {
            return response()->json([
                'ok' => false,
                'message' => 'Согласование лимита недоступно для своей компании.',
            ], 422);
        }

        $assessment = $approvalService->submit(
            $contractor,
            $request->user(),
            $request->string('reason')->toString() ?: null,
        );

        return response()->json([
            'ok' => true,
            'assessment_id' => $assessment->id,
            'limit_approval' => $approvalService->pendingPayloadFor($contractor->refresh()),
            'can_request_limit_approval' => false,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:customer,carrier,contractor,both'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = $request->get('q', '');
        $type = $request->filled('type') ? (string) $request->get('type') : null;
        $limit = $request->get('limit', 100);

        $contractorsQuery = Contractor::query()->visibleTo($request->user(), $type);

        // Apply search query
        if ($query !== '') {
            $contractorsQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('inn', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");

                if (Schema::hasColumn('contractors', 'full_name')) {
                    $q->orWhere('full_name', 'like', "%{$query}%");
                }
            });
        }

        // Get contractors with basic info
        $contractors = $contractorsQuery
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Contractor $contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'full_name' => Schema::hasColumn('contractors', 'full_name') ? $contractor->full_name : null,
                'type' => $contractor->type,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'is_active' => $contractor->is_active,
                'is_own_company' => $contractor->is_own_company ?? false,
                'debt_limit' => $contractor->debt_limit,
                'debt_limit_currency' => $contractor->debt_limit_currency ?? 'RUB',
                'stop_on_limit' => (bool) ($contractor->stop_on_limit ?? false),
                'default_customer_payment_form' => $contractor->default_customer_payment_form,
                'default_customer_payment_schedule' => $contractor->default_customer_payment_schedule,
                'default_customer_payment_term' => $contractor->default_customer_payment_term,
                'default_carrier_payment_form' => $contractor->default_carrier_payment_form,
                'default_carrier_payment_schedule' => $contractor->default_carrier_payment_schedule,
                'default_carrier_payment_term' => $contractor->default_carrier_payment_term,
                'cooperation_terms_notes' => $contractor->cooperation_terms_notes,
                'default_customer_norms_penalties' => $contractor->default_customer_norms_penalties,
                'default_carrier_norms_penalties' => $contractor->default_carrier_norms_penalties,
            ]);

        return response()->json([
            'contractors' => $contractors,
            'count' => $contractors->count(),
        ]);
    }

    private function renderPage(Request $request, ?Contractor $selectedContractor = null): Response
    {
        /** @var ContractorCreditService $creditService */
        $creditService = app(ContractorCreditService::class);
        $hasContactsTable = Schema::hasTable('contractor_contacts');
        $hasInteractionsTable = Schema::hasTable('contractor_interactions');
        $hasDocumentsTable = Schema::hasTable('contractor_documents');
        $hasOrderDocumentsTable = Schema::hasTable('order_documents');

        // Фильтр списка — только query string (в теле PATCH есть поле type — тип контрагента).
        $type = trim((string) $request->query('type', ''));

        // Apply visibility scope with type filter parameter
        // The scope will handle type-specific visibility rules
        $contractorsQuery = Contractor::query()->visibleTo($request->user(), $type);

        // Add search functionality
        $search = $request->input('search', '');
        if ($search) {
            $contractorsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('inn', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if (Schema::hasColumn('contractors', 'owner_id')) {
                    $query->orWhereHas('owner', function ($ownerQuery) use ($search): void {
                        $ownerQuery->where('name', 'like', "%{$search}%");
                    });
                }
            });
        }

        // Note: Type filtering is handled within the visibleTo scope
        // based on the $type parameter passed above

        if ($hasContactsTable) {
            $contractorsQuery->withCount('contacts');
        }

        $contractorsQuery->withCount(['customerOrders', 'carrierOrders']);

        if (Schema::hasColumn('contractors', 'owner_id')) {
            $contractorsQuery->with('owner:id,name');
        }

        if ($hasContactsTable) {
            $contractorsQuery->with([
                'contacts' => static function ($query): void {
                    $query->select('id', 'contractor_id', 'full_name', 'phone', 'is_primary')
                        ->orderByDesc('is_primary')
                        ->orderBy('full_name');
                },
            ]);
        }

        try {
            $contractorsCollection = $contractorsQuery
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get();
        } catch (QueryException $exception) {
            if ($this->isMissingTableException($exception, 'contractor_contacts')) {
                $fallbackQuery = Contractor::query()
                    ->withCount(['customerOrders', 'carrierOrders']);

                if (Schema::hasColumn('contractors', 'owner_id')) {
                    $fallbackQuery->with('owner:id,name');
                }

                $contractorsCollection = $fallbackQuery
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get();
                $hasContactsTable = false;
            } else {
                throw $exception;
            }
        }

        $debtMap = $creditService->currentDebtByContractorIds($contractorsCollection->pluck('id')->all());

        /** @var ContractorOperationalStatusService $statusService */
        $statusService = app(ContractorOperationalStatusService::class);
        $statusService->enrichManyForDisplay($contractorsCollection);

        $contractors = $contractorsCollection
            ->map(function (Contractor $contractor) use ($hasContactsTable, $creditService, $debtMap, $statusService): array {
                $primary = $this->primaryContactNameAndPhoneForGrid($contractor, $hasContactsTable);

                return [
                    'id' => $contractor->id,
                    'name' => $contractor->name,
                    'type' => $contractor->type,
                    'type_label' => $this->contractorTypeLabel($contractor->type),
                    'inn' => $contractor->inn,
                    'phone' => $primary['phone'],
                    'email' => $contractor->email,
                    'is_active' => $contractor->is_active,
                    'work_status' => $contractor->work_status ?? ContractorWorkStatus::ACTIVE,
                    'work_pause_is_automatic' => (bool) ($contractor->work_pause_is_automatic ?? false),
                    'is_verified' => (bool) $contractor->is_verified,
                    'verified_at' => optional($contractor->verified_at)?->toIso8601String(),
                    'verification_valid_until' => optional($statusService->verificationValidUntil($contractor->verified_at))?->toDateString(),
                    'is_own_company' => $contractor->is_own_company ?? false,
                    'status_text' => $statusService->resolveStatusText($contractor),
                    'status_badge_class' => $statusService->resolveStatusBadge($contractor)['badge'],
                    'activity_types_label' => $this->implodeActivityTypes($contractor->activity_types),
                    'primary_contact' => $primary['name'],
                    'owner_id' => Schema::hasColumn('contractors', 'owner_id')
                        ? $contractor->owner_id
                        : null,
                    'owner_name' => Schema::hasColumn('contractors', 'owner_id')
                        ? ($contractor->owner?->name ?? '')
                        : '',
                    'debt_limit' => $contractor->debt_limit,
                    'debt_limit_currency' => $contractor->debt_limit_currency ?? 'RUB',
                    'stop_on_limit' => (bool) ($contractor->stop_on_limit ?? false),
                    'current_debt' => $debtMap[$contractor->id] ?? 0,
                    'debt_limit_reached' => $creditService->isBlockedByDebtLimit($contractor, $debtMap[$contractor->id] ?? 0),
                    'contacts_count' => $hasContactsTable ? $contractor->contacts_count : 0,
                    'orders_count' => $contractor->customer_orders_count + $contractor->carrier_orders_count,
                ];
            })
            ->values();

        // Add pagination metadata
        $pagination = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $contractors->count(),
            'total' => $contractors->count(),
            'from' => $contractors->isEmpty() ? 0 : 1,
            'to' => $contractors->count(),
            'links' => [],
        ];

        $contractorDetails = null;

        if ($selectedContractor !== null) {
            $selectedContractor = $statusService->sync($selectedContractor);

            $relations = [];

            if ($hasContactsTable) {
                $relations[] = 'contacts';
            }

            if ($hasInteractionsTable) {
                $relations[] = 'interactions.author:id,name';
                $relations[] = 'interactions.contact:id,full_name,role_in_deal';
            }

            if (Schema::hasTable('contractor_portraits')) {
                $relations[] = 'portrait';
            }

            if ($hasDocumentsTable) {
                $relations[] = 'documents';
            }

            if ($relations !== []) {
                $selectedContractor->load($relations);
            }

            $orderSelect = [
                'id',
                'order_number',
                'status',
                'order_date',
                'customer_rate',
                'customer_id',
                'carrier_id',
            ];
            if (Schema::hasColumn('orders', 'carrier_rate')) {
                $orderSelect[] = 'carrier_rate';
            }

            $orderRows = DB::table('orders')
                ->select($orderSelect)
                ->where(function ($query) use ($selectedContractor): void {
                    $query->where('customer_id', $selectedContractor->id)
                        ->orWhere('carrier_id', $selectedContractor->id);
                })
                ->orderByDesc('order_date')
                ->limit(20)
                ->get();

            $carrierRateByOrderId = CarrierRateFromFinancialTerms::sumsByOrderId(
                $orderRows->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            );

            $orders = $orderRows
                ->map(function (object $order) use ($selectedContractor, $carrierRateByOrderId): array {
                    $carrierRate = Schema::hasColumn('orders', 'carrier_rate')
                        ? ($order->carrier_rate ?? null)
                        : null;
                    $computedCarrierRate = $carrierRateByOrderId->get((int) $order->id);
                    if ($computedCarrierRate !== null) {
                        $carrierRate = $computedCarrierRate;
                    }

                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'order_date' => $order->order_date,
                        'customer_rate' => $order->customer_rate,
                        'carrier_rate' => $carrierRate,
                        'relation' => (int) $order->customer_id === $selectedContractor->id ? 'customer' : 'carrier',
                    ];
                })
                ->values();

            $currentDebt = $creditService->currentDebtForContractor($selectedContractor->id);
            $relatedOrderDocuments = collect();

            if ($hasOrderDocumentsTable) {
                $documentDateColumn = Schema::hasColumn('order_documents', 'document_date');

                $relatedOrderDocuments = DB::table('order_documents')
                    ->join('orders', 'orders.id', '=', 'order_documents.order_id')
                    ->select(
                        'order_documents.id',
                        'order_documents.order_id',
                        'order_documents.type',
                        'order_documents.document_group',
                        'order_documents.number',
                        'order_documents.original_name',
                        'order_documents.status',
                        'order_documents.signature_status',
                        'order_documents.file_path',
                        'orders.order_number',
                        'orders.customer_id',
                        'orders.carrier_id',
                    )
                    ->when(
                        $documentDateColumn,
                        fn ($query) => $query->addSelect('order_documents.document_date')
                    )
                    ->where(function ($query) use ($selectedContractor): void {
                        $query->where('orders.customer_id', $selectedContractor->id)
                            ->orWhere('orders.carrier_id', $selectedContractor->id);
                    })
                    ->when(
                        Schema::hasColumn('orders', 'deleted_at'),
                        fn ($query) => $query->whereNull('orders.deleted_at')
                    )
                    ->when(
                        $documentDateColumn,
                        fn ($query) => $query->orderByDesc('order_documents.document_date')
                    )
                    ->orderByDesc('order_documents.id')
                    ->limit(30)
                    ->get()
                    ->map(fn (object $document): array => [
                        'id' => $document->id,
                        'order_id' => $document->order_id,
                        'order_number' => $document->order_number,
                        'type' => $document->type,
                        'document_group' => $document->document_group,
                        'number' => $document->number,
                        'original_name' => $document->original_name,
                        'document_date' => $document->document_date ?? null,
                        'status' => $document->status,
                        'signature_status' => $document->signature_status,
                        'file_path' => $document->file_path,
                        'relation' => (int) $document->customer_id === $selectedContractor->id ? 'customer' : 'carrier',
                    ])
                    ->values();
            }

            $contractorDetails = [
                ...$selectedContractor->toArray(),
                'print_form_profile' => app(ContractorPrintFormProfileResolver::class)->resolve($selectedContractor),
                'print_form_editor' => app(ContractorPrintFormChangeRequestService::class)->editorPayloadForContractor(
                    $selectedContractor,
                    $request->user(),
                    $request->string('print_party')->toString(),
                ),
                'current_debt' => $currentDebt,
                'debt_limit_reached' => $creditService->isBlockedByDebtLimit($selectedContractor, $currentDebt),
                'limit_approval' => app(ContractorLimitApprovalService::class)->pendingPayloadFor($selectedContractor),
                'can_request_limit_approval' => app(ContractorLimitApprovalService::class)->canSubmit($selectedContractor, $currentDebt),
                'limit_approval_reason' => app(ContractorLimitApprovalService::class)->resolveReason($selectedContractor, $currentDebt),
                'contacts' => $hasContactsTable ? $selectedContractor->contacts->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_primary' => $contact->is_primary,
                    'is_traklo_primary' => Schema::hasColumn('contractor_contacts', 'is_traklo_primary')
                        ? (bool) $contact->is_traklo_primary
                        : false,
                    'has_traklo_user' => Schema::hasColumn('users', 'contractor_contact_id')
                        && User::query()->where('contractor_contact_id', $contact->id)->exists(),
                    'is_decision_maker' => Schema::hasColumn('contractor_contacts', 'is_decision_maker')
                        ? $contact->is_decision_maker
                        : false,
                    'role_in_deal' => Schema::hasColumn('contractor_contacts', 'role_in_deal')
                        ? ($contact->role_in_deal ?: ($contact->is_decision_maker ? 'decision_maker' : 'unknown'))
                        : 'unknown',
                    'role_in_deal_label' => ContractorPortraitDictionary::label(
                        'role_in_deal',
                        Schema::hasColumn('contractor_contacts', 'role_in_deal')
                            ? ($contact->role_in_deal ?: ($contact->is_decision_maker ? 'decision_maker' : 'unknown'))
                            : 'unknown',
                    ),
                    'communication_notes' => Schema::hasColumn('contractor_contacts', 'communication_notes')
                        ? $contact->communication_notes
                        : null,
                    'notes' => $contact->notes,
                ])->values() : collect(),
                'interactions' => $hasInteractionsTable ? $selectedContractor->interactions->map(fn ($interaction): array => [
                    'id' => $interaction->id,
                    'contractor_contact_id' => $interaction->contractor_contact_id,
                    'contact_name' => $interaction->contact?->full_name,
                    'contacted_at' => optional($interaction->contacted_at)?->toIso8601String(),
                    'channel' => $interaction->channel,
                    'outcome_code' => $interaction->outcome_code,
                    'outcome_label' => ContractorPortraitDictionary::label('outcome_code', $interaction->outcome_code),
                    'next_contact_at' => optional($interaction->next_contact_at)?->toIso8601String(),
                    'subject' => $interaction->subject,
                    'summary' => $interaction->summary,
                    'result' => $interaction->result,
                    'objection_tags' => is_array($interaction->objection_tags) ? $interaction->objection_tags : [],
                    'author_name' => $interaction->author?->name,
                ])->values() : collect(),
                'portrait' => Schema::hasTable('contractor_portraits')
                    ? app(ContractorPortraitService::class)->serializePortrait($selectedContractor->portrait, $selectedContractor)
                    : null,
                'insight_drafts' => Schema::hasTable('contractor_insight_drafts')
                    ? app(ContractorInsightDraftService::class)->serializePendingForContractor($selectedContractor)->values()->all()
                    : [],
                'documents' => $hasDocumentsTable
                    ? $selectedContractor->documents->map(
                        fn ($document): array => $this->serializeContractorDocument($document, $selectedContractor),
                    )->values()
                    : collect(),
                'orders' => $orders,
                'order_documents' => $relatedOrderDocuments,
            ];
        }

        return Inertia::render('Contractors/Index', [
            'contractors' => $contractors,
            'selectedContractor' => $contractorDetails,
            'pagination' => $pagination,
            'activityTypeOptions' => $this->activityTypeOptions(),
            'legalFormOptions' => [
                ['value' => 'ooo', 'label' => 'ООО'],
                ['value' => 'zao', 'label' => 'ЗАО'],
                ['value' => 'ao', 'label' => 'АО'],
                ['value' => 'ip', 'label' => 'ИП'],
                ['value' => 'samozanyaty', 'label' => 'Самозанятый'],
                ['value' => 'other', 'label' => 'Другое'],
            ],
            'users' => $this->ownerUserOptionsForContractorForm($selectedContractor?->owner_id),
            'contractorColumns' => ContractorTableColumns::options(),
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'currencyOptions' => CurrencyDictionary::options(),
            'paymentFormOptions' => PaymentFormDictionary::options(),
            'edoProviderOptions' => EdoProviderDictionary::options(),
            'workStatusOptions' => collect(ContractorWorkStatus::manualValues())
                ->map(fn (string $value): array => [
                    'value' => $value,
                    'label' => ContractorWorkStatus::label($value),
                ])
                ->values()
                ->all(),
            'portraitOptions' => [
                'communication_style' => ContractorPortraitDictionary::optionsFor('communication_style'),
                'price_sensitivity' => ContractorPortraitDictionary::optionsFor('price_sensitivity'),
                'preferred_channel' => ContractorPortraitDictionary::optionsFor('preferred_channel'),
                'decision_cadence' => ContractorPortraitDictionary::optionsFor('decision_cadence'),
                'relationship_trust' => ContractorPortraitDictionary::optionsFor('relationship_trust'),
                'role_in_deal' => ContractorPortraitDictionary::optionsFor('role_in_deal'),
                'outcome_code' => ContractorPortraitDictionary::optionsFor('outcome_code'),
                'objection_tag' => ContractorPortraitDictionary::optionsFor('objection_tag'),
            ],
            'initialTab' => in_array($request->string('tab')->toString(), [
                'general', 'requisites', 'cooperation', 'contacts', 'portrait', 'communications', 'orders', 'documents',
            ], true) ? $request->string('tab')->toString() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationPayload(Contractor $contractor, ContractorOperationalStatusService $statusService): array
    {
        return [
            'is_verified' => (bool) $contractor->is_verified,
            'verified_at' => optional($contractor->verified_at)?->toIso8601String(),
            'verification_valid_until' => optional($statusService->verificationValidUntil($contractor->verified_at))?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractContractorAttributes(array $validated, ?Contractor $existingContractor = null): array
    {
        unset($validated['contacts'], $validated['interactions'], $validated['documents']);

        foreach ([
            'legal_form',
            'debt_limit',
            'short_description',
            'signer_name_nominative',
            'signer_name_prepositional',
            'signer_position',
            'signer_authority_basis',
            'edo_provider',
            'edo_number',
            'default_customer_payment_form',
            'default_customer_payment_term',
            'default_customer_payment_schedule',
            'default_carrier_payment_form',
            'default_carrier_payment_term',
            'default_carrier_payment_schedule',
            'cooperation_terms_notes',
            'default_customer_norms_penalties',
            'default_carrier_norms_penalties',
            'non_resident_corr_bank_name',
            'non_resident_corr_bank_swift',
            'non_resident_corr_settlement_account',
            'non_resident_corr_bank_account',
            'cnaps_code',
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
        ] as $nullableField) {
            if (($validated[$nullableField] ?? null) === '') {
                $validated[$nullableField] = null;
            }
        }

        if ($this->hasAnyPaymentScheduleInput($validated, 'default_customer')) {
            $resolvedCustomer = $this->resolvePaymentSchedule(
                Arr::get($validated, 'default_customer_payment_schedule'),
                Arr::get($validated, 'default_customer_payment_term'),
                Arr::get($validated, 'default_customer_payment_form'),
            );

            if ($resolvedCustomer === null) {
                unset(
                    $validated['default_customer_payment_schedule'],
                    $validated['default_customer_payment_term'],
                    $validated['default_customer_payment_form'],
                );
            } else {
                $validated['default_customer_payment_schedule'] = $resolvedCustomer;
                $validated['default_customer_payment_term'] = $this->paymentScheduleSummary($resolvedCustomer);
            }
        }

        if ($this->hasAnyPaymentScheduleInput($validated, 'default_carrier')) {
            $resolvedCarrier = $this->resolvePaymentSchedule(
                Arr::get($validated, 'default_carrier_payment_schedule'),
                Arr::get($validated, 'default_carrier_payment_term'),
                Arr::get($validated, 'default_carrier_payment_form'),
            );

            if ($resolvedCarrier === null) {
                unset(
                    $validated['default_carrier_payment_schedule'],
                    $validated['default_carrier_payment_term'],
                    $validated['default_carrier_payment_form'],
                );
            } else {
                $validated['default_carrier_payment_schedule'] = $resolvedCarrier;
                $validated['default_carrier_payment_term'] = $this->paymentScheduleSummary($resolvedCarrier);
            }
        }

        if (($validated['debt_limit_currency'] ?? null) === '') {
            $validated['debt_limit_currency'] = 'RUB';
        }

        if (array_key_exists('owner_id', $validated) && $validated['owner_id'] === '') {
            $validated['owner_id'] = null;
        }

        if (array_key_exists('activity_types', $validated)) {
            $validated['activity_types'] = collect($validated['activity_types'] ?? [])
                ->map(fn (mixed $value): string => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (array_key_exists('bank_accounts', $validated)) {
            $validated['bank_accounts'] = $this->normalizeBankAccounts($validated['bank_accounts']);
            $validated = $this->applyLegacyBankFields($validated, $existingContractor);
        }

        if (array_key_exists('default_customer_norms_penalties', $validated)) {
            $validated['default_customer_norms_penalties'] = PartyNormsPenalties::normalizeForStorage(
                is_array($validated['default_customer_norms_penalties'] ?? null)
                    ? $validated['default_customer_norms_penalties']
                    : null,
            );
        }

        if (array_key_exists('default_carrier_norms_penalties', $validated)) {
            $validated['default_carrier_norms_penalties'] = PartyNormsPenalties::normalizeForStorage(
                is_array($validated['default_carrier_norms_penalties'] ?? null)
                    ? $validated['default_carrier_norms_penalties']
                    : null,
            );
        }

        if (! (bool) ($validated['is_non_resident'] ?? false)) {
            foreach (['non_resident_corr_bank_name', 'non_resident_corr_bank_swift', 'non_resident_corr_settlement_account', 'non_resident_corr_bank_account', 'cnaps_code'] as $column) {
                if (Schema::hasColumn('contractors', $column)) {
                    $validated[$column] = null;
                }
            }
        }

        if (! Schema::hasColumn('contractors', 'is_own_company')) {
            unset($validated['is_own_company']);
        }

        $contractorName = trim((string) ($validated['name'] ?? $existingContractor?->name ?? ''));
        if (OwnFleetCatalog::isVirtualFleetContractorName($contractorName)) {
            $validated['is_own_company'] = false;
        }

        if ((bool) ($validated['is_own_company'] ?? $existingContractor?->is_own_company ?? false)) {
            $validated = $this->applyOwnCompanyOperationalExemptions($validated);
        }

        unset($validated['is_verified'], $validated['verified_at']);

        if (array_key_exists('work_status', $validated)) {
            $workStatus = (string) $validated['work_status'];

            if (in_array($workStatus, ContractorWorkStatus::manualValues(), true)) {
                $validated['work_status'] = $workStatus;
                $validated['work_pause_is_automatic'] = false;
            } else {
                unset($validated['work_status']);
            }
        }

        foreach ([
            'type',
            'work_status',
            'work_pause_is_automatic',
            'verified_at',
            'debt_limit',
            'debt_limit_currency',
            'stop_on_limit',
            'short_description',
            'activity_types',
            'specializations',
            'transport_requirements',
            'signer_name_nominative',
            'signer_name_prepositional',
            'signer_position',
            'signer_authority_basis',
            'default_customer_payment_form',
            'default_customer_payment_term',
            'default_customer_payment_schedule',
            'default_carrier_payment_form',
            'default_carrier_payment_term',
            'default_carrier_payment_schedule',
            'cooperation_terms_notes',
            'default_customer_norms_penalties',
            'default_carrier_norms_penalties',
            'bank_accounts',
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
            'mail_sync_domains',
            'is_own_company',
            'ati_id',
            'rating',
            'completed_orders',
            'metadata',
        ] as $column) {
            if (! Schema::hasColumn('contractors', $column)) {
                unset($validated[$column]);
            }
        }

        return $validated;
    }

    /**
     * Для «своей компании» операционные статусы (архив, работа, лимиты) не применяются.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyOwnCompanyOperationalExemptions(array $validated): array
    {
        $validated['is_active'] = true;
        $validated['work_status'] = ContractorWorkStatus::ACTIVE;
        $validated['work_pause_is_automatic'] = false;
        $validated['stop_on_limit'] = false;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasAnyPaymentScheduleInput(array $validated, string $prefix): bool
    {
        return array_key_exists("{$prefix}_payment_schedule", $validated)
            || array_key_exists("{$prefix}_payment_term", $validated)
            || array_key_exists("{$prefix}_payment_form", $validated);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBankAccounts(mixed $bankAccounts): array
    {
        if (! is_array($bankAccounts)) {
            return [];
        }

        $normalized = collect($bankAccounts)
            ->map(fn (mixed $row): array => $this->sanitizeBankAccountRow($row))
            ->filter(fn (array $row): bool => $this->bankAccountHasMeaningfulData($row))
            ->values();

        $primaryIndex = $normalized->search(fn (array $row): bool => (bool) ($row['is_primary'] ?? false));
        if ($primaryIndex === false && $normalized->isNotEmpty()) {
            $first = $normalized->first();
            $first['is_primary'] = true;
            $normalized[0] = $first;
        }

        return $normalized
            ->map(function (array $row): array {
                if (! isset($row['id']) || blank($row['id'])) {
                    $row['id'] = uniqid('bank_', true);
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeBankAccountRow(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }

        return [
            'id' => blank($row['id'] ?? null) ? null : (string) $row['id'],
            'label' => blank($row['label'] ?? null) ? null : trim((string) $row['label']),
            'country_code' => blank($row['country_code'] ?? null) ? null : strtoupper(trim((string) $row['country_code'])),
            'currency' => blank($row['currency'] ?? null) ? null : strtoupper(trim((string) $row['currency'])),
            'bank_name' => blank($row['bank_name'] ?? null) ? null : trim((string) $row['bank_name']),
            'bik' => blank($row['bik'] ?? null) ? null : preg_replace('/\D/u', '', (string) $row['bik']),
            'account_number' => blank($row['account_number'] ?? null) ? null : preg_replace('/\D/u', '', (string) $row['account_number']),
            'correspondent_account' => blank($row['correspondent_account'] ?? null) ? null : preg_replace('/\D/u', '', (string) $row['correspondent_account']),
            'swift' => blank($row['swift'] ?? null) ? null : strtoupper(trim((string) $row['swift'])),
            'iban' => blank($row['iban'] ?? null) ? null : strtoupper(str_replace(' ', '', trim((string) $row['iban']))),
            'is_primary' => (bool) ($row['is_primary'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function bankAccountHasMeaningfulData(array $row): bool
    {
        return filled($row['bank_name'] ?? null)
            || filled($row['bik'] ?? null)
            || filled($row['account_number'] ?? null)
            || filled($row['correspondent_account'] ?? null)
            || filled($row['swift'] ?? null)
            || filled($row['iban'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyLegacyBankFields(array $validated, ?Contractor $existingContractor = null): array
    {
        $bankAccounts = is_array($validated['bank_accounts'] ?? null) ? $validated['bank_accounts'] : [];
        if ($bankAccounts === []) {
            return $validated;
        }

        $primary = collect($bankAccounts)->first(fn (array $row): bool => (bool) ($row['is_primary'] ?? false))
            ?? $bankAccounts[0];

        $legacyFields = ['bank_name', 'bik', 'account_number', 'correspondent_account'];
        $incomingHasLegacy = collect($legacyFields)
            ->contains(fn (string $field): bool => filled($validated[$field] ?? null));
        $existingHasLegacy = $existingContractor !== null && collect($legacyFields)
            ->contains(fn (string $field): bool => filled($existingContractor->getAttribute($field)));

        // Старые поля поддерживаем только там, где они уже реально использовались.
        if (! $incomingHasLegacy && ! $existingHasLegacy) {
            foreach ($legacyFields as $field) {
                unset($validated[$field]);
            }

            return $validated;
        }

        $validated['bank_name'] = $primary['bank_name'] ?? ($validated['bank_name'] ?? null);
        $validated['bik'] = $primary['bik'] ?? ($validated['bik'] ?? null);
        $validated['account_number'] = $primary['account_number'] ?? ($validated['account_number'] ?? null);
        $validated['correspondent_account'] = $primary['correspondent_account'] ?? ($validated['correspondent_account'] ?? null);

        return $validated;
    }

    /**
     * @return array<int, string>
     */
    private function activityTypeOptions(): array
    {
        if (Schema::hasTable('contractor_activity_types')) {
            return ContractorActivityType::query()
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        if (! Schema::hasColumn('contractors', 'activity_types')) {
            return [];
        }

        return Contractor::query()
            ->whereNotNull('activity_types')
            ->pluck('activity_types')
            ->flatMap(function (mixed $value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            })
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $schedule
     * @return array<string, int|string|bool>|null
     */
    private function resolvePaymentSchedule(?array $schedule, ?string $legacyTerm, ?string $paymentForm): ?array
    {
        $normalized = $schedule !== null
            ? $this->normalizePaymentSchedule($schedule)
            : $this->parsePaymentTermPreset($legacyTerm);

        if (! $this->hasMeaningfulPaymentSchedule($normalized) && blank($paymentForm)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, int|string|bool>
     */
    private function normalizePaymentSchedule(array $schedule): array
    {
        $normalized = [
            'has_prepayment' => false,
            'prepayment_ratio' => 50,
            'prepayment_days' => 0,
            'prepayment_mode' => 'fttn',
            'postpayment_days' => 0,
            'postpayment_mode' => 'ottn',
        ];

        $normalized = array_merge($normalized, $schedule);
        $normalized['has_prepayment'] = filter_var($normalized['has_prepayment'], FILTER_VALIDATE_BOOLEAN);
        $normalized['prepayment_ratio'] = max(1, min(99, (int) ($normalized['prepayment_ratio'] ?? 50)));
        $normalized['prepayment_days'] = max(0, (int) ($normalized['prepayment_days'] ?? 0));
        $normalized['postpayment_days'] = max(0, (int) ($normalized['postpayment_days'] ?? 0));
        $normalized['prepayment_mode'] = in_array($normalized['prepayment_mode'], ['fttn', 'fttn_receipt', 'ottn'], true)
            ? $normalized['prepayment_mode']
            : 'fttn';
        $normalized['postpayment_mode'] = in_array($normalized['postpayment_mode'], ['fttn', 'fttn_receipt', 'ottn'], true)
            ? $normalized['postpayment_mode']
            : 'ottn';

        return $normalized;
    }

    /**
     * @return array<string, int|string|bool>|null
     */
    private function parsePaymentTermPreset(?string $term): ?array
    {
        if (blank($term)) {
            return null;
        }

        $normalized = mb_strtoupper(trim($term));

        if (preg_match('/^(\d{1,2})%\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN)\s*\/\s*(\d{1,2})%\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN)$/u', $normalized, $matches) === 1) {
            return $this->normalizePaymentSchedule([
                'has_prepayment' => true,
                'prepayment_ratio' => (int) $matches[1],
                'prepayment_days' => (int) $matches[2],
                'prepayment_mode' => mb_strtolower($matches[3]),
                'postpayment_days' => (int) $matches[5],
                'postpayment_mode' => mb_strtolower($matches[6]),
            ]);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN)\s*\/\s*(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN)$/u', $normalized, $matches) === 1) {
            return $this->normalizePaymentSchedule([
                'has_prepayment' => true,
                'prepayment_ratio' => (int) $matches[1],
                'prepayment_days' => (int) $matches[3],
                'prepayment_mode' => mb_strtolower($matches[4]),
                'postpayment_days' => (int) $matches[5],
                'postpayment_mode' => mb_strtolower($matches[6]),
            ]);
        }

        if (preg_match('/^(\d+)\s+ДН\s+(FTTN(?:_RECEIPT)?|OTTN)$/u', $normalized, $matches) === 1) {
            return $this->normalizePaymentSchedule([
                'postpayment_days' => (int) $matches[1],
                'postpayment_mode' => mb_strtolower($matches[2]),
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, int|string|bool>|null  $schedule
     */
    private function hasMeaningfulPaymentSchedule(?array $schedule): bool
    {
        if ($schedule === null) {
            return false;
        }

        if (($schedule['has_prepayment'] ?? false) === true) {
            return true;
        }

        return (int) ($schedule['postpayment_days'] ?? 0) > 0;
    }

    /**
     * @param  array<string, int|string|bool>|null  $schedule
     */
    private function paymentScheduleSummary(?array $schedule): ?string
    {
        if (! $this->hasMeaningfulPaymentSchedule($schedule)) {
            return null;
        }

        if (($schedule['has_prepayment'] ?? false) === true) {
            $prepaymentRatio = (int) ($schedule['prepayment_ratio'] ?? 50);
            $postpaymentRatio = max(0, 100 - $prepaymentRatio);

            return sprintf(
                '%d%% %d дн %s / %d%% %d дн %s',
                $prepaymentRatio,
                (int) ($schedule['prepayment_days'] ?? 0),
                $this->paymentModeSummaryToken((string) ($schedule['prepayment_mode'] ?? 'fttn')),
                $postpaymentRatio,
                (int) ($schedule['postpayment_days'] ?? 0),
                $this->paymentModeSummaryToken((string) ($schedule['postpayment_mode'] ?? 'ottn')),
            );
        }

        return sprintf(
            '%d дн %s',
            (int) ($schedule['postpayment_days'] ?? 0),
            $this->paymentModeSummaryToken((string) ($schedule['postpayment_mode'] ?? 'ottn')),
        );
    }

    private function paymentModeSummaryToken(string $mode): string
    {
        return match (mb_strtolower(trim($mode))) {
            'fttn_receipt' => 'FTTN_RECEIPT',
            default => strtoupper(trim($mode)),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncNestedData(Contractor $contractor, array $validated, ?int $userId): void
    {
        if (Schema::hasTable('contractor_contacts')
            && array_key_exists('contacts', $validated)
            && is_array($validated['contacts'])) {
            $contractor->contacts()->delete();

            foreach ($validated['contacts'] as $contact) {
                if (! Schema::hasColumn('contractor_contacts', 'is_decision_maker')) {
                    unset($contact['is_decision_maker']);
                }

                if (Schema::hasColumn('contractor_contacts', 'role_in_deal')) {
                    $role = $contact['role_in_deal'] ?? null;
                    if ($role === 'decision_maker' && Schema::hasColumn('contractor_contacts', 'is_decision_maker')) {
                        $contact['is_decision_maker'] = true;
                    }
                } else {
                    unset($contact['role_in_deal'], $contact['communication_notes']);
                }

                $contractor->contacts()->create($contact);
            }
        }

        if (Schema::hasTable('contractor_interactions')
            && array_key_exists('interactions', $validated)
            && is_array($validated['interactions'])) {
            $contractor->interactions()->delete();

            foreach ($validated['interactions'] as $interaction) {
                $contractor->interactions()->create([
                    ...$interaction,
                    'created_by' => $userId,
                ]);
            }
        }

        if (array_key_exists('documents', $validated) && is_array($validated['documents'])) {
            $this->contractorDocumentSync->sync($contractor, $validated['documents'], $userId);
        }
    }

    public function downloadPartnerCard(Contractor $contractor, ContractorPartnerCardService $partnerCardService): BinaryFileResponse
    {
        abort_unless($contractor->is_own_company, 404);

        $generated = $partnerCardService->generate($contractor);
        $absolutePath = Storage::disk($generated['disk'])->path($generated['path']);

        return response()
            ->download($absolutePath, $generated['download_name'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }

    public function previewDocument(Request $request, Contractor $contractor, ContractorDocument $contractorDocument): HttpResponse
    {
        abort_unless($contractorDocument->contractor_id === $contractor->id, 404);
        abort_unless(
            Schema::hasTable('contractor_documents') && Schema::hasColumn('contractor_documents', 'file_path'),
            404,
        );
        abort_if(blank($contractorDocument->file_path), 404);

        $driver = $contractorDocument->storage_driver ?: DocumentStorageService::DRIVER_LOCAL;
        $contents = $this->documentStorage->get((string) $contractorDocument->file_path, $driver);
        $mime = (string) ($contractorDocument->mime_type ?: 'application/octet-stream');
        $filename = $contractorDocument->original_name ?: basename((string) $contractorDocument->file_path);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=60',
            'Content-Disposition' => $this->inlineDispositionForMime($mime, $filename),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContractorDocument(ContractorDocument $document, Contractor $contractor): array
    {
        $hasFile = Schema::hasColumn('contractor_documents', 'file_path') && filled($document->file_path);

        return [
            'id' => $document->id,
            'type' => $document->type,
            'title' => $document->title,
            'number' => $document->number,
            'document_date' => optional($document->document_date)?->toDateString(),
            'status' => $document->status,
            'notes' => $document->notes,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'created_at' => optional($document->created_at)?->toIso8601String(),
            'preview_url' => $hasFile
                ? route('contractors.documents.preview', [$contractor, $document])
                : null,
        ];
    }

    private function inlineDispositionForMime(string $mime, string $filename): string
    {
        $asciiName = preg_replace('/[\r\n"]/', '', $filename) ?: 'file';
        $inline = str_starts_with($mime, 'image/')
            || $mime === 'application/pdf';
        $mode = $inline ? 'inline' : 'attachment';

        return sprintf('%s; filename="%s"', $mode, addcslashes($asciiName, '"\\'));
    }

    private function contractorHasOrders(Contractor $contractor): bool
    {
        $ordersQuery = DB::table('orders')
            ->where(function ($query) use ($contractor): void {
                $query->where('customer_id', $contractor->id)
                    ->orWhere('carrier_id', $contractor->id);
            });

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $ordersQuery->whereNull('deleted_at');
        }

        return $ordersQuery->exists();
    }

    private function isMissingTableException(QueryException $exception, string $table): bool
    {
        $message = strtolower($exception->getMessage());
        $needle = strtolower($table);

        return str_contains($message, 'table') && str_contains($message, $needle);
    }

    /**
     * @return array{search?: string, type?: string, page?: int}
     */
    private function listContext(Request $request): array
    {
        $context = [];

        // Только query string: в теле PATCH/PUT есть поле `type` (вид контрагента) —
        // через input() оно ошибочно попадало в редирект как фильтр списка.
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $context['search'] = $search;
        }

        $type = trim((string) $request->query('type', ''));
        if ($type !== '') {
            $context['type'] = $type;
        }

        $page = (int) $request->query('page', 0);
        if ($page > 0) {
            $context['page'] = $page;
        }

        return $context;
    }

    private function contractorTypeLabel(?string $type): string
    {
        return match ($type) {
            'customer' => 'Заказчик',
            'carrier' => 'Перевозчик',
            'contractor' => 'Подрядчик',
            'both' => 'Заказчик и перевозчик',
            default => 'Не указан',
        };
    }

    private function implodeActivityTypes(mixed $activityTypes): string
    {
        if (! is_array($activityTypes) || $activityTypes === []) {
            return '—';
        }

        $items = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $activityTypes,
        )));

        return $items === [] ? '—' : implode(', ', $items);
    }

    /**
     * Реестр: «Основной контакт» = ФИО из вкладки «Контакты» (is_primary), «Телефон» = его телефон; иначе поля карточки.
     *
     * @return array{name: string, phone: string}
     */
    private function primaryContactNameAndPhoneForGrid(Contractor $contractor, bool $hasContactsTable): array
    {
        if ($hasContactsTable && $contractor->relationLoaded('contacts')) {
            $primary = $contractor->contacts->first(static fn ($c): bool => (bool) ($c->is_primary ?? false))
                ?? $contractor->contacts->first();

            if ($primary !== null) {
                $name = trim((string) ($primary->full_name ?? ''));
                $phone = trim((string) ($primary->phone ?? ''));
                $fallbackPhone = trim((string) ($contractor->phone ?? ''));

                return [
                    'name' => $name !== '' ? $name : '—',
                    'phone' => $phone !== '' ? $phone : $fallbackPhone,
                ];
            }
        }

        $legacyName = trim((string) ($contractor->contact_person ?? ''));
        $legacyPhone = trim((string) ($contractor->phone ?? ''));

        return [
            'name' => $legacyName !== '' ? $legacyName : '—',
            'phone' => $legacyPhone,
        ];
    }

    private function resolvePrimaryContact(Contractor $contractor): string
    {
        $name = trim((string) ($contractor->contact_person ?? ''));
        $phone = trim((string) ($contractor->contact_person_phone ?? $contractor->phone ?? ''));

        if ($name !== '' && $phone !== '') {
            return $name.' · '.$phone;
        }

        if ($name !== '') {
            return $name;
        }

        if ($phone !== '') {
            return $phone;
        }

        return '—';
    }

    /**
     * Пользователи для поля «Владелец»: только активные; текущий владелец карточки
     * добавляется, если он неактивен (чтобы значение не «терялось» в select).
     *
     * @return list<array{id: int, name: string}>
     */
    private function ownerUserOptionsForContractorForm(?int $retainOwnerId): array
    {
        $query = User::query()->select('id', 'name')->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        $rows = $query->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->all();

        if ($retainOwnerId === null || ! Schema::hasColumn('users', 'is_active')) {
            return $rows;
        }

        $retainId = (int) $retainOwnerId;
        $alreadyListed = collect($rows)->contains(fn (array $row): bool => (int) $row['id'] === $retainId);

        if ($alreadyListed) {
            return $rows;
        }

        $retained = User::query()->select('id', 'name', 'is_active')->find($retainId);

        if ($retained === null) {
            return $rows;
        }

        $name = (string) $retained->name;
        if (! $retained->is_active) {
            $name .= ' (не активен)';
        }

        array_unshift($rows, [
            'id' => (int) $retained->id,
            'name' => $name,
        ]);

        return $rows;
    }
}
