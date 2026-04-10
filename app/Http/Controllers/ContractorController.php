<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractorRequest;
use App\Http\Requests\UpdateContractorRequest;
use App\Models\Contractor;
use App\Models\ContractorActivityType;
use App\Models\User;
use App\Services\Checko\ContractorScoringService;
use App\Services\ContractorCreditService;
use App\Services\DaDataService;
use App\Support\CarrierRateFromFinancialTerms;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
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

            $contractor = Contractor::query()->create([
                ...$this->extractContractorAttributes($validated),
                'owner_id' => $request->user()?->id,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($contractor, $validated, $request->user()?->id);

            return $contractor;
        });

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
                ...$this->extractContractorAttributes($validated),
                'updated_by' => $request->user()?->id,
            ]);

            $this->syncNestedData($contractor, $validated, $request->user()?->id);
        });

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

    public function suggestAddress(Request $request, DaDataService $daDataService): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'suggestions' => $daDataService->suggestAddress($request->string('query')->toString()),
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
        $validated = $request->validate([
            'contractor_ids' => ['required', 'array', 'min:1'],
            'contractor_ids.*' => ['integer', 'exists:contractors,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $contractorIds = $validated['contractor_ids'];
        $ownerId = $validated['owner_id'];

        $updatedCount = Contractor::query()
            ->whereIn('id', $contractorIds)
            ->update(['owner_id' => $ownerId]);

        return response()->json([
            'message' => 'Владелец успешно обновлён для '.$updatedCount.' контрагентов.',
            'updated_count' => $updatedCount,
        ]);
    }

    public function scoring(Request $request, Contractor $contractor, ContractorScoringService $scoringService): JsonResponse
    {
        $refresh = $request->boolean('refresh');

        return response()->json($scoringService->buildPayload($contractor, $refresh));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:customer,carrier,both'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = $request->get('q', '');
        $type = $request->get('type', 'customer');
        $limit = $request->get('limit', 100);

        $contractorsQuery = Contractor::query();

        // Как в мастере заказа: перевозчики — type carrier или both; клиенты — customer или both.
        if ($type === 'customer') {
            $contractorsQuery->whereIn('type', ['customer', 'both']);
        } elseif ($type === 'carrier') {
            $contractorsQuery->whereIn('type', ['carrier', 'both']);
        } elseif ($type === 'both') {
            $contractorsQuery->whereIn('type', ['customer', 'both']);
        }

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

        // Get type filter from request
        $type = $request->input('type', '');

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
            });
        }

        // Note: Type filtering is handled within the visibleTo scope
        // based on the $type parameter passed above

        if ($hasContactsTable) {
            $contractorsQuery->withCount('contacts');
        }

        $contractorsQuery->withCount(['customerOrders', 'carrierOrders']);

        // Add pagination - load 10 contractors per page
        $perPage = 10;
        $page = $request->input('page', 1);

        try {
            $contractorsPaginator = $contractorsQuery
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate($perPage, ['*'], 'page', $page);

            $contractorsCollection = $contractorsPaginator->getCollection();
        } catch (QueryException $exception) {
            if ($this->isMissingTableException($exception, 'contractor_contacts')) {
                $contractorsPaginator = Contractor::query()
                    ->withCount(['customerOrders', 'carrierOrders'])
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->paginate($perPage, ['*'], 'page', $page);

                $contractorsCollection = $contractorsPaginator->getCollection();
                $hasContactsTable = false;
            } else {
                throw $exception;
            }
        }

        $debtMap = $creditService->currentDebtByContractorIds($contractorsCollection->pluck('id')->all());

        $contractors = $contractorsCollection
            ->map(fn (Contractor $contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'type' => $contractor->type,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'is_active' => $contractor->is_active,
                'is_own_company' => $contractor->is_own_company ?? false,
                'debt_limit' => $contractor->debt_limit,
                'debt_limit_currency' => $contractor->debt_limit_currency ?? 'RUB',
                'stop_on_limit' => (bool) ($contractor->stop_on_limit ?? false),
                'current_debt' => $debtMap[$contractor->id] ?? 0,
                'debt_limit_reached' => $creditService->isBlockedByDebtLimit($contractor, $debtMap[$contractor->id] ?? 0),
                'contacts_count' => $hasContactsTable ? $contractor->contacts_count : 0,
                'orders_count' => $contractor->customer_orders_count + $contractor->carrier_orders_count,
            ])
            ->values();

        // Add pagination metadata
        $pagination = [
            'current_page' => $contractorsPaginator->currentPage(),
            'last_page' => $contractorsPaginator->lastPage(),
            'per_page' => $contractorsPaginator->perPage(),
            'total' => $contractorsPaginator->total(),
            'from' => $contractorsPaginator->firstItem(),
            'to' => $contractorsPaginator->lastItem(),
            'links' => $contractorsPaginator->linkCollection()->toArray(),
        ];

        $contractorDetails = null;

        if ($selectedContractor !== null) {
            $relations = [];

            if ($hasContactsTable) {
                $relations[] = 'contacts';
            }

            if ($hasInteractionsTable) {
                $relations[] = 'interactions.author:id,name';
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
                'current_debt' => $currentDebt,
                'debt_limit_reached' => $creditService->isBlockedByDebtLimit($selectedContractor, $currentDebt),
                'contacts' => $hasContactsTable ? $selectedContractor->contacts->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_primary' => $contact->is_primary,
                    'notes' => $contact->notes,
                ])->values() : collect(),
                'interactions' => $hasInteractionsTable ? $selectedContractor->interactions->map(fn ($interaction): array => [
                    'id' => $interaction->id,
                    'contacted_at' => optional($interaction->contacted_at)?->toIso8601String(),
                    'channel' => $interaction->channel,
                    'subject' => $interaction->subject,
                    'summary' => $interaction->summary,
                    'result' => $interaction->result,
                    'author_name' => $interaction->author?->name,
                ])->values() : collect(),
                'documents' => $hasDocumentsTable ? $selectedContractor->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'title' => $document->title,
                    'number' => $document->number,
                    'document_date' => optional($document->document_date)?->toDateString(),
                    'status' => $document->status,
                    'notes' => $document->notes,
                ])->values() : collect(),
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
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractContractorAttributes(array $validated): array
    {
        unset($validated['contacts'], $validated['interactions'], $validated['documents']);

        foreach ([
            'debt_limit',
            'short_description',
            'signer_name_nominative',
            'signer_name_prepositional',
            'signer_authority_basis',
            'default_customer_payment_form',
            'default_customer_payment_term',
            'default_customer_payment_schedule',
            'default_carrier_payment_form',
            'default_carrier_payment_term',
            'default_carrier_payment_schedule',
            'cooperation_terms_notes',
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

        if (! Schema::hasColumn('contractors', 'is_own_company')) {
            unset($validated['is_own_company']);
        }

        foreach ([
            'debt_limit',
            'debt_limit_currency',
            'stop_on_limit',
            'short_description',
            'activity_types',
            'signer_name_nominative',
            'signer_name_prepositional',
            'signer_authority_basis',
            'default_customer_payment_form',
            'default_customer_payment_term',
            'default_customer_payment_schedule',
            'default_carrier_payment_form',
            'default_carrier_payment_term',
            'default_carrier_payment_schedule',
            'cooperation_terms_notes',
        ] as $column) {
            if (! Schema::hasColumn('contractors', $column)) {
                unset($validated[$column]);
            }
        }

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
        $normalized['prepayment_mode'] = in_array($normalized['prepayment_mode'], ['fttn', 'ottn'], true)
            ? $normalized['prepayment_mode']
            : 'fttn';
        $normalized['postpayment_mode'] = in_array($normalized['postpayment_mode'], ['fttn', 'ottn'], true)
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

        if (preg_match('/^(\d{1,2})\/(\d{1,2}),\s*(\d+)\s+ДН\s+(FTTN|OTTN)\s*\/\s*(\d+)\s+ДН\s+(FTTN|OTTN)$/u', $normalized, $matches) === 1) {
            return $this->normalizePaymentSchedule([
                'has_prepayment' => true,
                'prepayment_ratio' => (int) $matches[1],
                'prepayment_days' => (int) $matches[3],
                'prepayment_mode' => mb_strtolower($matches[4]),
                'postpayment_days' => (int) $matches[5],
                'postpayment_mode' => mb_strtolower($matches[6]),
            ]);
        }

        if (preg_match('/^(\d+)\s+ДН\s+(FTTN|OTTN)$/u', $normalized, $matches) === 1) {
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
                '%d/%d, %d дн %s / %d дн %s',
                $prepaymentRatio,
                $postpaymentRatio,
                (int) ($schedule['prepayment_days'] ?? 0),
                strtoupper((string) ($schedule['prepayment_mode'] ?? 'fttn')),
                (int) ($schedule['postpayment_days'] ?? 0),
                strtoupper((string) ($schedule['postpayment_mode'] ?? 'ottn')),
            );
        }

        return sprintf(
            '%d дн %s',
            (int) ($schedule['postpayment_days'] ?? 0),
            strtoupper((string) ($schedule['postpayment_mode'] ?? 'ottn')),
        );
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

        if (Schema::hasTable('contractor_documents')
            && array_key_exists('documents', $validated)
            && is_array($validated['documents'])) {
            $contractor->documents()->delete();

            foreach ($validated['documents'] as $document) {
                $contractor->documents()->create([
                    ...$document,
                    'created_by' => $userId,
                ]);
            }
        }
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

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $context['search'] = $search;
        }

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $context['type'] = $type;
        }

        $page = $request->integer('page');
        if ($page > 0) {
            $context['page'] = $page;
        }

        return $context;
    }
}
