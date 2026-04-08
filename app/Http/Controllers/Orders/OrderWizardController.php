<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInlineOrderContractorRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateInlineOrderFieldRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Services\ContractorCreditService;
use App\Services\DaDataService;
use App\Services\OrderCompensationService;
use App\Services\OrderDocumentRequirementService;
use App\Services\OrderPrintFormDraftService;
use App\Services\OrderWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderWizardController extends Controller
{
    public function create(Request $request): Response
    {
        return $this->renderPage($request);
    }

    public function store(StoreOrderRequest $request, OrderWizardService $orderWizardService): RedirectResponse
    {
        $order = $orderWizardService->create($request->validated(), $request->user());

        return to_route('orders.edit', $order);
    }

    public function edit(Request $request, Order $order): Response
    {
        return $this->renderPage($request, $this->loadOrderForEditing($order));
    }

    public function update(UpdateOrderRequest $request, Order $order, OrderWizardService $orderWizardService): RedirectResponse
    {
        $order = $orderWizardService->update($order, $request->validated(), $request->user());

        return to_route('orders.edit', $order);
    }

    public function inlineUpdate(
        UpdateInlineOrderFieldRequest $request,
        Order $order,
        OrderCompensationService $orderCompensationService,
    ): RedirectResponse {
        abort_unless($this->canEditInlineField($request, $order), 403);

        $payload = $request->validatedPayload();

        $previousOrderDate = $order->order_date?->toDateString();

        $order->forceFill([
            $payload['field'] => $payload['value'],
            'updated_by' => $request->user()?->id,
        ])->save();

        if (in_array($payload['field'], ['customer_rate', 'carrier_rate', 'additional_expenses'], true)) {
            $this->syncFinancialTermsFromOrderRates($order->fresh());
        }

        if (in_array($payload['field'], ['customer_payment_form', 'carrier_payment_form'], true)) {
            $this->syncFinancialTermsFromOrderRates($order->fresh());
        }

        if (in_array($payload['field'], [
            'customer_rate',
            'carrier_rate',
            'additional_expenses',
            'customer_payment_form',
            'carrier_payment_form',
            'order_date',
        ], true)) {
            $dealTypeChanged = in_array($payload['field'], ['customer_payment_form', 'carrier_payment_form'], true);
            $orderCompensationService->recalculateImpactedPeriods($order->fresh(), null, $previousOrderDate, $dealTypeChanged);
        }

        return to_route('orders.index');
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

            if (Schema::hasTable('order_documents')) {
                $order->documents()->delete();
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
            'name' => $request->string('name')->toString(),
            'inn' => $request->string('inn')->toString() ?: null,
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
            'order_date' => ['nullable', 'date'],
            'client_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'carrier_id' => ['nullable', 'integer', 'exists:contractors,id'],
        ]);

        $calculation = $orderCompensationService->calculateRealtime($request->all());

        return response()->json($calculation);
    }

    public function generateDocumentDraft(
        Request $request,
        Order $order,
        PrintFormTemplate $printFormTemplate,
        OrderPrintFormDraftService $draftService,
    ): BinaryFileResponse {
        abort_unless($this->canEditInlineField($request, $order), 403);
        abort_if($printFormTemplate->entity_type !== 'order', 422, 'Черновик можно сформировать только для шаблона заказа.');
        abort_if(blank($printFormTemplate->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');
        abort_unless($this->isTemplateAvailableForOrder($printFormTemplate, $order), 404);

        $generatedFile = $draftService->generate($printFormTemplate, $this->loadOrderForEditing($order));

        return response()->download(
            Storage::disk($generatedFile['disk'])->path($generatedFile['path']),
            $generatedFile['download_name']
        );
    }

    private function renderPage(Request $request, ?Order $order = null): Response
    {
        /** @var ContractorCreditService $creditService */
        $creditService = app(ContractorCreditService::class);
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

        return Inertia::render('Orders/Wizard', [
            'order' => $order === null ? null : $this->serializeOrder($order),
            'contractors' => $contractors->values(),
            'ownCompanies' => Schema::hasColumn('contractors', 'is_own_company')
                ? $contractors->where('is_own_company', true)->values()
                : collect(),
            'cargoTypeOptions' => [
                ['value' => 'general', 'label' => 'Общий груз'],
                ['value' => 'dangerous', 'label' => 'Опасный груз'],
                ['value' => 'temperature_controlled', 'label' => 'Температурный режим'],
                ['value' => 'oversized', 'label' => 'Негабаритный груз'],
                ['value' => 'fragile', 'label' => 'Хрупкий груз'],
            ],
            'packageTypeOptions' => [
                ['value' => 'pallet', 'label' => 'Паллета'],
                ['value' => 'box', 'label' => 'Короб'],
                ['value' => 'crate', 'label' => 'Ящик'],
                ['value' => 'roll', 'label' => 'Рулон'],
                ['value' => 'bag', 'label' => 'Мешок'],
            ],
            'currencyOptions' => [
                ['value' => 'RUB', 'label' => 'RUB'],
                ['value' => 'USD', 'label' => 'USD'],
                ['value' => 'CNY', 'label' => 'CNY'],
                ['value' => 'EUR', 'label' => 'EUR'],
            ],
            'documentTypeOptions' => $documentRequirementService->documentTypeOptions(),
            'documentPartyOptions' => $documentRequirementService->partyOptions(),
            'requiredDocumentRules' => $documentRequirementService->requirementRules(),
            'requiredDocumentChecklist' => $documentRequirementService->checklistForOrder($order),
            'orderStatusOptions' => [
                ['value' => 'new', 'label' => 'Новый заказ'],
                ['value' => 'in_progress', 'label' => 'Выполняется'],
                ['value' => 'documents', 'label' => 'Документы'],
                ['value' => 'payment', 'label' => 'Оплата'],
                ['value' => 'closed', 'label' => 'Закрыта'],
                ['value' => 'draft', 'label' => 'Черновик (legacy)'],
                ['value' => 'pending', 'label' => 'На согласовании (legacy)'],
                ['value' => 'confirmed', 'label' => 'Подтвержден (legacy)'],
                ['value' => 'completed', 'label' => 'Завершен (legacy)'],
                ['value' => 'cancelled', 'label' => 'Отменена'],
            ],
            'documentStatusOptions' => [
                ['value' => 'draft', 'label' => 'Черновик'],
                ['value' => 'pending', 'label' => 'Ожидает'],
                ['value' => 'signed', 'label' => 'Подписан'],
                ['value' => 'sent', 'label' => 'Отправлен'],
            ],
            'printFormTemplateOptions' => $this->availablePrintFormTemplates($order)->values(),
            'currentUser' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
            ],
            'cargoTitleSuggestions' => Cargo::query()
                ->whereNotNull('title')
                ->where('title', '!=', '')
                ->distinct()
                ->orderBy('title')
                ->limit(30)
                ->pluck('title')
                ->values(),
        ]);
    }

    private function canDeleteOrder(Request $request, Order $order): bool
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

        return $order->manager_id === $user->id
            && $order->loading_date === null;
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

        return $order->manager_id === $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        $financialTerm = Schema::hasTable('financial_terms') ? $order->financialTerms->first() : null;
        $paymentTermsConfig = $this->decodePaymentTermsConfig($order->payment_terms);
        $routePointHasAddressColumn = Schema::hasColumn('route_points', 'address');
        $routePointHasMetadataColumn = Schema::hasColumn('route_points', 'metadata');
        $cargoItems = $this->orderCargoItems($order);
        $documents = Schema::hasTable('order_documents') ? $order->documents : collect();
        $statusLogs = Schema::hasTable('order_status_logs') ? $order->statusLogs : collect();
        $routePoints = $order->legs
            ->sortBy('sequence')
            ->flatMap(function ($leg) use ($routePointHasAddressColumn, $routePointHasMetadataColumn) {
                return $leg->routePoints
                    ->sortBy('sequence')
                    ->map(fn ($point): array => [
                        'id' => $point->id,
                        'stage' => $leg->description,
                        'leg_sequence' => $leg->sequence,
                        'type' => $point->type,
                        'sequence' => $point->sequence,
                        'address' => $routePointHasAddressColumn
                            ? $point->address
                            : data_get($point->metadata, 'address', $point->instructions),
                        'normalized_data' => $routePointHasMetadataColumn
                            ? (data_get($point->metadata, 'normalized_data', []))
                            : ($point->normalized_data ?? []),
                        'planned_date' => optional($point->planned_date)?->toDateString(),
                        'actual_date' => optional($point->actual_date)?->toDateString(),
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

        $contractorsCosts = collect($this->normalizeContractorsCosts($order, $financialTerm))
            ->map(fn (array $cost): array => [
                'stage' => $cost['stage'] ?? null,
                'contractor_id' => $cost['contractor_id'] ?? null,
                'amount' => $cost['amount'] ?? null,
                'currency' => $cost['currency'] ?? 'RUB',
                'payment_form' => $cost['payment_form'] ?? 'no_vat',
                'payment_schedule' => $cost['payment_schedule'] ?? [],
            ])
            ->values()
            ->all();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_date' => optional($order->order_date)?->toDateString(),
            'client_id' => $order->customer_id,
            'own_company_id' => $order->own_company_id,
            'responsible_id' => $order->manager_id,
            'payment_terms' => $order->payment_terms,
            'special_notes' => $order->special_notes,
            'performers' => $order->performers ?? [],
            'route_points' => $routePoints,
            'cargo_items' => $cargoItems->map(fn ($cargo): array => [
                'id' => $cargo->id,
                'name' => $cargo->title,
                'description' => $cargo->description,
                'weight_kg' => $cargo->weight,
                'volume_m3' => $cargo->volume,
                'package_type' => $cargo->packing_type,
                'package_count' => $cargo->package_count ?? $cargo->pallet_count,
                'dangerous_goods' => $cargo->is_hazardous,
                'dangerous_class' => $cargo->hazard_class,
                'hs_code' => $cargo->hs_code,
                'cargo_type' => $cargo->cargo_type ?: 'general',
            ])->values()->all(),
            'financial_term' => [
                'client_price' => $order->customer_rate !== null
                    ? $order->customer_rate
                    : $financialTerm?->client_price,
                'client_currency' => $financialTerm?->client_currency ?? 'RUB',
                'client_payment_form' => $order->customer_payment_form ?? 'vat',
                'client_request_mode' => data_get($paymentTermsConfig, 'client.request_mode', 'single_request'),
                'client_payment_schedule' => $paymentTermsConfig['client']['payment_schedule'] ?? [],
                'contractors_costs' => $contractorsCosts,
                'additional_costs' => $financialTerm?->additional_costs ?? [],
                'kpi_percent' => $order->kpi_percent,
            ],
            'documents' => $documents->map(fn ($document): array => [
                'id' => $document->id,
                'type' => $document->type,
                'party' => data_get($document->metadata, 'party', 'internal'),
                'requirement_key' => data_get($document->metadata, 'requirement_key'),
                'number' => $document->number,
                'document_date' => optional($document->document_date)?->toDateString(),
                'status' => $document->status,
                'original_name' => $document->original_name,
                'file_path' => $document->file_path,
                'generated_pdf_path' => $document->generated_pdf_path,
                'template_id' => $document->template_id,
            ])->values()->all(),
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
     * @return list<string>
     */
    private function contractorSelectColumns(): array
    {
        $columns = ['id', 'name', 'inn', 'phone', 'email', 'type'];

        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $columns[] = 'is_own_company';
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
            'ogrn',
            'bank_name',
            'bik',
            'account_number',
            'correspondent_account',
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
        $relations = ['client', 'ownCompany', 'legs.routePoints'];

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
     * @return Collection<int, array{id:int,name:string,code:string,document_type:string,party:string,contractor_id:int|null,contractor_name:string|null,is_default:bool}>
     */
    private function availablePrintFormTemplates(?Order $order = null): Collection
    {
        if (! Schema::hasTable('print_form_templates')) {
            return collect();
        }

        $contractorIds = $this->orderTemplateContractorIds($order);

        return PrintFormTemplate::query()
            ->when(
                Schema::hasColumn('print_form_templates', 'contractor_id'),
                fn ($query) => $query->with(['contractor:id,name'])
            )
            ->where('entity_type', 'order')
            ->where('is_active', true)
            ->whereNotNull('file_path')
            ->where(function ($query) use ($contractorIds): void {
                $query->whereNull('contractor_id');

                if ($contractorIds !== []) {
                    $query->orWhereIn('contractor_id', $contractorIds);
                }
            })
            ->orderByRaw('case when contractor_id is null then 1 else 0 end')
            ->orderByDesc('is_default')
            ->orderBy('document_type')
            ->orderBy('name')
            ->get()
            ->map(fn (PrintFormTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'document_type' => $template->document_type,
                'party' => $template->party,
                'contractor_id' => $template->contractor_id,
                'contractor_name' => $template->contractor?->name,
                'is_default' => (bool) $template->is_default,
            ])
            ->values();
    }

    private function isTemplateAvailableForOrder(PrintFormTemplate $template, Order $order): bool
    {
        if (! $template->is_active || blank($template->file_path) || $template->entity_type !== 'order') {
            return false;
        }

        if ($template->contractor_id === null) {
            return true;
        }

        return in_array($template->contractor_id, $this->orderTemplateContractorIds($order), true);
    }

    /**
     * @return list<int>
     */
    private function orderTemplateContractorIds(?Order $order): array
    {
        if ($order === null) {
            return [];
        }

        return collect([
            $order->customer_id,
            $order->carrier_id,
            $order->own_company_id,
        ])->filter(fn (mixed $value): bool => is_int($value) || ctype_digit((string) $value))
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
     * @return list<array<string, mixed>>
     */
    private function normalizeContractorsCosts(Order $order, ?FinancialTerm $financialTerm): array
    {
        $contractorsCosts = $financialTerm?->contractors_costs ?? [];

        if (! is_array($contractorsCosts)) {
            $contractorsCosts = [];
        }

        // Если есть performers в заказе, используем их как основной источник данных
        $performers = collect($order->performers ?? [])->values();

        if ($performers->isNotEmpty()) {
            // Создаем contractors_costs на основе performers
            $contractorsCosts = $performers
                ->map(function ($performer, int $index) use ($financialTerm, $order, $contractorsCosts): array {
                    // Проверяем, что $performer - массив
                    if (! is_array($performer)) {
                        return [
                            'stage' => 'leg_'.($index + 1),
                            'contractor_id' => null,
                            'amount' => null,
                            'currency' => $financialTerm?->client_currency ?? 'RUB',
                            'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                            'payment_schedule' => [],
                        ];
                    }

                    // Ищем существующую запись для этого этапа
                    $existingCost = collect($contractorsCosts)
                        ->firstWhere('stage', $performer['stage'] ?? 'leg_'.($index + 1));

                    return [
                        'stage' => $performer['stage'] ?? 'leg_'.($index + 1),
                        'contractor_id' => $performer['contractor_id'] ?? null,
                        'amount' => $existingCost['amount'] ?? null,
                        'currency' => $existingCost['currency'] ?? $financialTerm?->client_currency ?? 'RUB',
                        'payment_form' => $existingCost['payment_form'] ?? $order->carrier_payment_form ?? 'no_vat',
                        'payment_schedule' => $existingCost['payment_schedule'] ?? [],
                    ];
                })
                ->all();
        } elseif ($contractorsCosts === [] && ($order->carrier_id !== null || $order->carrier_rate !== null)) {
            // Только если нет performers и нет contractors_costs, но есть carrier_id в заказе
            // Проверяем, что в performers действительно нет данных
            $performers = collect($order->performers ?? [])->values();
            if ($performers->isEmpty()) {
                $contractorsCosts = [[
                    'stage' => 'leg_1',
                    'contractor_id' => $order->carrier_id,
                    'amount' => null,
                    'currency' => $financialTerm?->client_currency ?? 'RUB',
                    'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                    'payment_schedule' => [],
                ]];
            } else {
                // Если есть performers, используем их данные
                $contractorsCosts = $performers
                    ->map(function ($performer, int $index) use ($financialTerm, $order): array {
                        if (! is_array($performer)) {
                            return [
                                'stage' => 'leg_'.($index + 1),
                                'contractor_id' => null,
                                'amount' => null,
                                'currency' => $financialTerm?->client_currency ?? 'RUB',
                                'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                                'payment_schedule' => [],
                            ];
                        }

                        return [
                            'stage' => $performer['stage'] ?? 'leg_'.($index + 1),
                            'contractor_id' => $performer['contractor_id'] ?? null,
                            'amount' => null,
                            'currency' => $financialTerm?->client_currency ?? 'RUB',
                            'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                            'payment_schedule' => [],
                        ];
                    })
                    ->all();
            }
        } else {
            // Update existing contractors costs with current payment_form from order
            $contractorsCosts = collect($contractorsCosts)
                ->map(function (array $cost) use ($order): array {
                    $cost['payment_form'] = $order->carrier_payment_form ?? 'no_vat';

                    return $cost;
                })
                ->all();
        }

        return $this->mergeOrderCarrierRateIntoContractorsCosts($contractorsCosts, $order->carrier_rate);
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

        $financialTerm = FinancialTerm::query()->where('order_id', $order->id)->first();

        if ($financialTerm === null) {
            $attributes = [
                'order_id' => $order->id,
                'client_price' => $order->customer_rate,
                'client_currency' => 'RUB',
                'contractors_costs' => $this->normalizeContractorsCosts($order, null),
                'total_cost' => 0,
                'margin' => 0,
                'additional_costs' => [],
            ];

            if (Schema::hasColumn('financial_terms', 'client_payment_terms')) {
                $attributes['client_payment_terms'] = $order->customer_payment_term;
            }

            $financialTerm = FinancialTerm::query()->create($attributes);
        }

        if ($order->customer_rate !== null) {
            $financialTerm->client_price = $order->customer_rate;
        }

        $costs = $this->normalizeContractorsCosts($order, $financialTerm);
        $financialTerm->contractors_costs = $costs;

        $contractorsSum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));
        $additionalTotal = collect($financialTerm->additional_costs ?? [])
            ->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
        $financialTerm->total_cost = $contractorsSum + $additionalTotal;

        $kpiPercent = (float) ($order->kpi_percent ?? 0);
        $clientPrice = (float) ($order->customer_rate ?? $financialTerm->client_price ?? 0);
        $financialTerm->margin = ($clientPrice * (1 - ($kpiPercent / 100))) - $financialTerm->total_cost;

        $financialTerm->save();
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

        $sum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));

        if (abs(round((float) $carrierRate, 2) - round($sum, 2)) < 0.01) {
            return $costs;
        }

        if (count($costs) === 1) {
            $costs[0]['amount'] = (float) $carrierRate;

            return $costs;
        }

        $rest = collect($costs)->slice(1)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));
        $costs[0]['amount'] = max(0, (float) $carrierRate - $rest);

        return $costs;
    }

    /**
     * Оптимизированная загрузка контрагентов: только нужные для текущего заказа
     */
    private function loadRelevantContractors(?Order $order): Collection
    {
        $query = Contractor::query();

        // Если есть заказ, загружаем связанных контрагентов + топ активных + собственные компании
        if ($order) {
            $relatedIds = $this->getRelatedContractorIds($order);

            if (! empty($relatedIds)) {
                return $query->where(function ($q) use ($relatedIds) {
                    // Связанные контрагенты
                    $q->whereIn('id', $relatedIds)
                      // Или активные
                        ->orWhere('is_active', true)
                      // Или собственные компании (даже если не активны)
                        ->orWhere('is_own_company', true);
                })
                    ->orderByDesc('is_own_company')
                    ->orderBy('name')
                    ->limit(300) // Увеличено с 150 до 300
                    ->get($this->contractorSelectColumns());
            }
        }

        // Для нового заказа или заказа без связанных контрагентов - активные + собственные компании
        if (Schema::hasColumn('contractors', 'is_own_company')) {
            $query->where(function ($q): void {
                $q->where('is_active', true)
                    ->orWhere('is_own_company', true);
            })->orderByDesc('is_own_company');
        } else {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')
            ->limit(200) // Увеличено с 100 до 200
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
        }

        return array_unique(array_filter($ids));
    }
}
