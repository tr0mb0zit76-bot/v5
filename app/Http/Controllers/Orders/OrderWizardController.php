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
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Services\ContractorCreditService;
use App\Services\DaDataService;
use App\Services\OrderCompensationService;
use App\Services\OrderDocumentRequirementService;
use App\Services\OrderPrintFormDraftService;
use App\Services\OrderWizardService;
use App\Services\OrderWizardStateService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Support\CarrierPaymentTermResolver;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;

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
        Log::info('orders.update request received', [
            'order_id' => $order->id,
            'user_id' => $request->user()?->id,
            'client_id' => $request->input('client_id'),
            'performers_count' => count((array) $request->input('performers', [])),
        ]);

        $order = $orderWizardService->update($order, $request->validated(), $request->user());

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
        OrderCompensationService $orderCompensationService,
        OrderWizardStateService $orderWizardStateService,
    ): RedirectResponse {
        abort_unless($this->canEditInlineField($request, $order), 403);

        $payload = $request->validatedPayload();

        $previousOrderDate = $order->order_date?->toDateString();

        $fill = [
            'updated_by' => $request->user()?->id,
        ];

        if (Schema::hasColumn('orders', $payload['field'])) {
            $fill[$payload['field']] = $payload['value'];
        }

        $order->forceFill($fill)->save();

        $syncOrder = $order->fresh();
        if (! Schema::hasColumn('orders', $payload['field'])) {
            $syncOrder->setAttribute($payload['field'], $payload['value']);
        }

        if (in_array($payload['field'], ['customer_rate', 'carrier_rate', 'additional_expenses', 'insurance', 'bonus'], true)) {
            $this->syncFinancialTermsFromOrderRates($syncOrder);
        }

        if (in_array($payload['field'], ['customer_payment_form', 'carrier_payment_form'], true)) {
            $this->syncFinancialTermsFromOrderRates($syncOrder);
        }

        if (in_array($payload['field'], [
            'customer_rate',
            'carrier_rate',
            'additional_expenses',
            'insurance',
            'bonus',
            'customer_payment_form',
            'carrier_payment_form',
            'order_date',
        ], true)) {
            $dealTypeChanged = in_array($payload['field'], ['customer_payment_form', 'carrier_payment_form'], true);
            $orderCompensationService->recalculateImpactedPeriods($syncOrder, null, $previousOrderDate, $dealTypeChanged);
        }

        if (in_array($payload['field'], [
            'customer_rate',
            'carrier_rate',
            'additional_expenses',
            'insurance',
            'bonus',
            'customer_payment_form',
            'carrier_payment_form',
        ], true)) {
            $orderWizardStateService->mergeInlineIntoOrder($order->fresh(), $payload['field'], $payload['value']);
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
            'customer_payment_form' => ['nullable', 'string', 'max:50'],
            'carrier_payment_form' => ['nullable', 'string', 'max:50'],
            'contractors_costs' => ['nullable', 'array'],
            'contractors_costs.*.payment_form' => ['nullable', 'string', 'max:50'],
        ]);

        $calculation = $orderCompensationService->calculateRealtime($request->all());

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
        abort_unless($this->isTemplateAvailableForOrder($printFormTemplate, $order), 404);

        $generatedFile = $draftService->generate($printFormTemplate, $this->loadOrderForEditing($order));

        return $draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
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

        $canManageOrderDocuments = $order !== null && $this->canEditInlineField($request, $order);
        $canApproveOrderDocuments = $request->user() !== null
            && ($request->user()->isSupervisor() || $request->user()->isAdmin());

        return Inertia::render('Orders/Wizard', [
            'order' => $order === null ? null : $this->serializeOrder($order, $canManageOrderDocuments, $canApproveOrderDocuments),
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
            'orderDocumentWorkflow' => [
                'status_options' => OrderDocumentWorkflowStatus::options(),
            ],
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
    private function serializeOrder(Order $order, bool $canManageOrderDocuments, bool $canApproveOrderDocuments): array
    {
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
                        'address' => $routePointHasAddressColumn && filled($point->address)
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

        $performers = $this->serializePerformersPayload($order, $financialTerm);
        if ($useWizardState && is_array($wizardState) && filled($wizardState['performers'] ?? null)) {
            $normalizedPerformers = $this->normalizePerformersFromWizardState($wizardState['performers']);
            $wizardHasAssignedContractor = collect($normalizedPerformers)
                ->contains(fn (array $performer): bool => filled($performer['contractor_id'] ?? null));
            if ($normalizedPerformers !== [] && ($performers === [] || $wizardHasAssignedContractor)) {
                $performers = $normalizedPerformers;
            }
        }

        $financialTermForNormalize = $financialTerm;
        if ($useWizardState) {
            $financialTermForNormalize = new FinancialTerm([
                'contractors_costs' => $wizardFt['contractors_costs'] ?? [],
                'client_currency' => $wizardFt['client_currency'] ?? 'RUB',
            ]);
        }

        $contractorsCosts = collect($this->normalizeContractorsCosts($order, $financialTermForNormalize, $performers))
            ->map(fn (array $cost): array => [
                'stage' => $cost['stage'] ?? null,
                'contractor_id' => $cost['contractor_id'] ?? null,
                'amount' => $cost['amount'] ?? null,
                'currency' => $cost['currency'] ?? 'RUB',
                'payment_form' => $this->normalizePaymentFormCodeForWizard($cost['payment_form'] ?? null, 'no_vat'),
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
            'client_snapshot' => $order->relationLoaded('client') && $order->client !== null
                ? [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'inn' => $order->client->inn,
                    'type' => $order->client->type,
                ]
                : null,
            'own_company_id' => $order->own_company_id,
            'responsible_id' => $order->manager_id,
            'responsible_name' => $order->relationLoaded('manager') ? $order->manager?->name : null,
            'payment_terms' => $order->payment_terms,
            'special_notes' => $order->special_notes,
            'additional_expenses' => Schema::hasColumn('orders', 'additional_expenses') ? $order->additional_expenses : null,
            'insurance' => Schema::hasColumn('orders', 'insurance') ? $order->insurance : null,
            'bonus' => Schema::hasColumn('orders', 'bonus') ? $order->bonus : null,
            'performers' => $performers,
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
                'client_price' => $useWizardState
                    ? ($wizardFt['client_price'] ?? $order->customer_rate)
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
                    'vat',
                ),
                'client_request_mode' => data_get($paymentTermsConfig, 'client.request_mode', 'single_request'),
                'client_payment_schedule' => $paymentTermsConfig['client']['payment_schedule'] ?? [],
                'contractors_costs' => $contractorsCosts,
                'additional_costs' => $useWizardState
                    ? ($wizardFt['additional_costs'] ?? $financialTerm?->additional_costs ?? [])
                    : ($financialTerm?->additional_costs ?? []),
                // Источник истины — пересчёт в orders.kpi_percent; снимок wizard_state отстаёт после inline/grid.
                'kpi_percent' => $order->kpi_percent ?? ($useWizardState ? ($wizardFt['kpi_percent'] ?? 0) : 0),
            ],
            'documents' => $documents->map(fn (OrderDocument $document): array => $this->serializeOrderDocument(
                $document,
                $order,
                $canManageOrderDocuments,
                $canApproveOrderDocuments
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
    ): array {
        $base = [
            'id' => $document->id,
            'type' => $document->type,
            'flow' => data_get($document->metadata, 'flow', 'uploaded'),
            'party' => data_get($document->metadata, 'party', 'internal'),
            'stage' => data_get($document->metadata, 'stage'),
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
            return $base;
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

        $finalUrl = filled($document->generated_pdf_path)
            ? route('orders.documents.download-final', [$order, $document])
            : null;

        return array_merge($base, [
            'is_print_workflow' => true,
            'source' => Schema::hasColumn('order_documents', 'source') ? $document->source : null,
            'workflow_status' => $workflowStatus,
            'workflow_status_label' => $workflowStatus ? OrderDocumentWorkflowStatus::label($workflowStatus) : null,
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
            'draft_download_url' => $draftUrl,
            'draft_preview_url' => $draftPreviewUrl,
            'final_pdf_download_url' => $finalUrl,
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
    private function normalizePaymentFormCodeForWizard(?string $value, string $default = 'vat'): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $trimmed = trim($value);
        if (in_array($trimmed, ['vat', 'no_vat', 'cash'], true)) {
            return $trimmed;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (str_contains($lower, 'без') && str_contains($lower, 'ндс')) {
            return 'no_vat';
        }

        if (str_contains($lower, 'нал')) {
            return 'cash';
        }

        if (str_contains($lower, 'ндс')) {
            return 'vat';
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
     * @param  list<array<string, mixed>>|mixed  $performers
     * @return list<array{stage: string|null, contractor_id: int|null}>
     */
    private function normalizePerformersFromWizardState(mixed $performers): array
    {
        if (! is_array($performers) || $performers === []) {
            return [];
        }

        return collect($performers)
            ->map(function (mixed $p): array {
                if (! is_array($p)) {
                    return ['stage' => null, 'contractor_id' => null];
                }

                return [
                    'stage' => $p['stage'] ?? null,
                    'contractor_id' => isset($p['contractor_id']) && $p['contractor_id'] !== null ? (int) $p['contractor_id'] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Должен совпадать с {@see OrderWizardService} для сопоставления этапов.
     */
    private function normalizeStageIdentifierForWizard(?string $stage): string
    {
        $value = trim((string) $stage);

        if ($value === '') {
            return 'leg_1';
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        return $value;
    }

    /**
     * Исполнители для мастера: плечи заказа; перевозчик — из назначения на плече, при отсутствии — из snapshot `financial_terms.contractors_costs`.
     *
     * @return list<array{stage: string|null, contractor_id: int|null}>
     */
    private function serializePerformersPayload(Order $order, ?FinancialTerm $financialTerm): array
    {
        $costRows = $financialTerm?->contractors_costs ?? [];
        if (! is_array($costRows)) {
            $costRows = [];
        }

        $costsByNormalizedStage = collect($costRows)
            ->keyBy(fn (array $cost): string => $this->normalizeStageIdentifierForWizard((string) ($cost['stage'] ?? 'leg_1')));

        if (Schema::hasTable('order_legs')) {
            if (Schema::hasTable('leg_contractor_assignments')) {
                $order->loadMissing(['legs.contractorAssignment']);
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

                    if (Schema::hasTable('leg_contractor_assignments')) {
                        $contractorId = $leg->contractorAssignment?->contractor_id;
                    }

                    if ($contractorId === null) {
                        $fromCost = $costsByNormalizedStage->get($normalized);
                        $contractorId = $fromCost['contractor_id'] ?? null;
                    }

                    if ($contractorId === null && $index === 0 && $order->carrier_id !== null) {
                        $contractorId = $order->carrier_id;
                    }

                    return [
                        'stage' => $leg->description,
                        'contractor_id' => $contractorId !== null ? (int) $contractorId : null,
                    ];
                })
                ->all()
            : [];

        if ($fromLegs !== []) {
            return $fromLegs;
        }

        if ($costRows !== []) {
            return collect($costRows)
                ->map(fn (array $cost): array => [
                    'stage' => $cost['stage'] ?? 'leg_1',
                    'contractor_id' => isset($cost['contractor_id']) && $cost['contractor_id'] !== null ? (int) $cost['contractor_id'] : null,
                ])
                ->values()
                ->all();
        }

        if ($order->carrier_id !== null) {
            return [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => (int) $order->carrier_id,
                ],
            ];
        }

        return [];
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
        $relations = ['client', 'ownCompany', 'manager', 'legs.routePoints'];

        if (Schema::hasTable('leg_contractor_assignments')) {
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

        $ids = collect([
            $order->customer_id,
            $order->carrier_id,
            $order->own_company_id,
        ]);

        if ($order->relationLoaded('legs') && Schema::hasTable('leg_contractor_assignments')) {
            foreach ($order->legs as $leg) {
                $cid = $leg->contractorAssignment?->contractor_id;
                if ($cid !== null) {
                    $ids->push($cid);
                }
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
     * @param  list<array{stage: string|null, contractor_id: int|null}>  $serializedPerformers
     * @return list<array<string, mixed>>
     */
    private function normalizeContractorsCosts(Order $order, ?FinancialTerm $financialTerm, array $serializedPerformers = []): array
    {
        $savedCosts = $financialTerm?->contractors_costs ?? [];

        if (! is_array($savedCosts)) {
            $savedCosts = [];
        }

        $contractorsCosts = collect($serializedPerformers)
            ->values()
            ->map(function ($performer, int $index) use ($financialTerm, $savedCosts, $order): array {
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

                $stageKey = $this->normalizeStageIdentifierForWizard((string) ($performer['stage'] ?? 'leg_'.($index + 1)));
                $existingCost = collect($savedCosts)
                    ->first(function (array $cost) use ($stageKey): bool {
                        return $this->normalizeStageIdentifierForWizard((string) ($cost['stage'] ?? '')) === $stageKey;
                    });

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
            $serializedPerformers = $this->serializePerformersPayload($order, null);
            $attributes = [
                'order_id' => $order->id,
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

            $financialTerm = FinancialTerm::query()->create($attributes);
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
        $financialTerm->margin = ($clientPrice * (1 - ($kpiPercent / 100))) - $financialTerm->total_cost;

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
            $raw = $order->getAttribute('payment_terms');
            $config = [];
            if (filled($raw)) {
                $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
                $config = is_array($decoded) ? $decoded : [];
            }

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
        }

        return array_unique(array_filter($ids));
    }
}
