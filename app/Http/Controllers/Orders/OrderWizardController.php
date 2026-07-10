<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInlineOrderContractorRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateInlineOrderFieldRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\PrintFormTemplate;
use App\Services\Commercial\LeadPrecalculationDocumentService;
use App\Services\DaDataService;
use App\Services\OrderCompensationService;
use App\Services\OrderIntakeGoldenLibraryService;
use App\Services\OrderNumberingService;
use App\Services\OrderPrintFormDraftService;
use App\Services\Orders\OrderBasedOnTemplateBuilder;
use App\Services\Orders\OrderDeletionService;
use App\Services\Orders\OrderInlineFieldUpdateService;
use App\Services\Orders\Wizard\OrderWizardFinancialTermsSyncService;
use App\Services\Orders\Wizard\OrderWizardIndexService;
use App\Services\Orders\Wizard\OrderWizardOrderAuthorization;
use App\Services\Orders\Wizard\OrderWizardPagePresenter;
use App\Services\OrderWizardService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Support\ContractorIdentity;
use App\Support\OrderAgentLexicon;
use App\Support\OrderDeleteAuthorization;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\OrderLeadPrecalculationSnapshotResolver;
use App\Support\OrderPrintFormContext;
use App\Support\OrderViewAuthorization;
use App\Support\PaymentFormDictionary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderWizardController extends Controller
{
    public function __construct(
        private readonly OrderWizardIndexService $indexService,
        private readonly OrderWizardFinancialTermsSyncService $financialTermsSyncService,
        private readonly OrderWizardOrderAuthorization $orderAuthorization,
        private readonly OrderWizardPagePresenter $pagePresenter,
    ) {}

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
        abort_unless(OrderViewAuthorization::userCanViewOrder($request->user(), $order), 403);

        return $this->renderPage($request, $this->loadOrderForEditing($order));
    }

    public function update(UpdateOrderRequest $request, Order $order, OrderWizardService $orderWizardService): RedirectResponse
    {
        abort_unless($this->orderAuthorization->canEditOrder($request, $order), 403);

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
        abort_unless($this->orderAuthorization->canEditOrder($request, $order), 403);

        $payload = $request->validatedPayload();
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $orderInlineFieldUpdateService->apply($user, $order, $payload['field'], $payload['value']);

        if ($request->boolean('wizard_context')) {
            return redirect()->route('orders.edit', $order);
        }

        return to_route('orders.index');
    }

    public function syncFinancialTermsFromOrderRatesForOrder(Order $order): void
    {
        $this->financialTermsSyncService->syncFromOrderRates($order);
    }

    public function destroy(Request $request, Order $order, OrderDeletionService $orderDeletionService): RedirectResponse
    {
        if ($order->trashed()) {
            return to_route('orders.index');
        }

        abort_unless($this->canDeleteOrder($request, $order), 403);

        $orderDeletionService->delete($order, fn (Order $target): Order => $this->loadOrderForEditing($target));

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
            'dispatcher_id' => ['nullable', 'integer', 'exists:users,id'],
            'compensation_owner_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'compensation_dispatcher_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
        abort_unless($this->orderAuthorization->canEditOrder($request, $order), 403);
        abort_if($printFormTemplate->entity_type !== 'order', 422, 'Черновик можно сформировать только для шаблона заказа.');
        abort_if(blank($printFormTemplate->file_path), 422, 'У шаблона не загружен исходный DOCX-файл.');

        $orderForCheck = $this->loadOrderForEditing($order);
        $isInternationalTransport = $request->has('is_international_transport')
            ? $request->boolean('is_international_transport')
            : null;
        $party = $request->query('print_party');
        $party = is_string($party) && in_array($party, ['customer', 'carrier'], true) ? $party : null;

        abort_unless(
            $this->pagePresenter->isTemplateAvailableForOrder($printFormTemplate, $orderForCheck, $party, $isInternationalTransport),
            422,
            'Шаблон недоступен для этого заказа. Проверьте тип перевозки (ВЭД), нашу компанию и перевозчика.'
        );

        $generatedFile = $draftService->generate(
            $printFormTemplate,
            $orderForCheck,
            true,
            OrderPrintFormContext::forTemplatePreview((int) $orderForCheck->id),
        );

        return $draftResponseBuilder->fromGeneratedFile($request, $generatedFile);
    }

    public function leadPrecalculationSnapshotDocument(
        Request $request,
        Order $order,
        LeadPrecalculationDocumentService $documentService,
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless(OrderDocumentAccessAuthorization::userMayViewDocuments($request->user(), $order), 403);
        abort_if(OrderLeadPrecalculationSnapshotResolver::rawSnapshot($order) === null, 404);

        $format = strtolower((string) $request->query('format', 'html'));
        $document = $documentService->renderForOrderSnapshot($order);

        if ($format === 'pdf') {
            $pdf = $documentService->renderPdfForOrderSnapshot($order);
            abort_if($pdf === null, 503, 'PDF недоступен: проверьте настройку Gotenberg.');

            $download = $request->boolean('download');
            $fileName = str_replace('.html', '.pdf', $document['file_name']);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$fileName.'"',
            ]);
        }

        return response($document['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $orderTemplate
     */
    private function renderPage(Request $request, ?Order $order = null, ?array $orderTemplate = null): Response
    {
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

        return Inertia::render('Orders/Wizard', $this->pagePresenter->props(
            $request,
            $order,
            $orderTemplate,
            $canManageOrderDocuments,
            $canApproveOrderDocuments,
        ));
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

    private function loadOrderForEditing(Order $order): Order
    {
        return $this->indexService->loadForEditing($order);
    }

    private function userCanUseOrderAsTemplate(Request $request, Order $order): bool
    {
        return OrderViewAuthorization::userCanViewOrder($request->user(), $order);
    }
}
