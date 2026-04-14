<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Services\CabinetNotifier;
use App\Services\OrderCompensationService;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderDocumentWorkflowController extends Controller
{
    public function __construct(
        private readonly OrderPrintDocumentWorkflowService $workflowService,
        private readonly PrintFormTemplateOrderEligibility $templateEligibility,
        private readonly CabinetNotifier $cabinetNotifier,
        private readonly PrintFormDraftResponseBuilder $draftResponseBuilder,
        private readonly OrderCompensationService $orderCompensationService,
    ) {}

    public function storeFromTemplate(Request $request, Order $order): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);

        $validated = $request->validate([
            'print_form_template_id' => ['required', 'integer', 'exists:print_form_templates,id'],
        ]);

        $template = PrintFormTemplate::query()->findOrFail($validated['print_form_template_id']);
        $order->loadMissing(['legs']);
        if (Schema::hasTable('leg_contractor_assignments')) {
            $order->loadMissing(['legs.contractorAssignment']);
        }

        abort_unless(
            $this->templateEligibility->isTemplateAvailableForOrder($template, $order),
            404,
            'Шаблон недоступен для этого заказа.'
        );

        try {
            $this->workflowService->createFromTemplate($order, $template, $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Черновик заявки сохранён в карточке заказа.']);
    }

    public function requestApproval(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        try {
            $this->workflowService->requestApproval($orderDocument, $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $orderDocument->refresh();
        $this->cabinetNotifier->notifyDocumentApprovalRequested($order, $orderDocument, $request->user());

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Документ отправлен руководителю на согласование.']);
    }

    public function approve(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanApproveDocuments($request);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        try {
            $this->workflowService->approve($orderDocument, $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Документ согласован.']);
    }

    public function reject(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanApproveDocuments($request);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->workflowService->reject($orderDocument, $request->user(), $validated['rejection_reason']);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Согласование отклонено, менеджер может исправить данные и отправить снова.']);
    }

    public function finalize(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ]);

        try {
            $this->workflowService->attachFinalPdf($orderDocument, $validated['pdf'], $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Финальный PDF прикреплён к заказу.']);
    }

    public function regenerateDraft(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        try {
            $this->workflowService->regenerateDraft($orderDocument, $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Черновик пересоздан из данных заказа.']);
    }

    public function previewDraft(Request $request, Order $order, OrderDocument $orderDocument): InertiaResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->file_path), 404);

        $workflowStatus = Schema::hasColumn('order_documents', 'workflow_status')
            ? $orderDocument->workflow_status
            : null;

        $canManage = $this->userCanManageOrderDocuments($request, $order);
        $canRequestApproval = $canManage && in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::DRAFT,
            OrderDocumentWorkflowStatus::REJECTED,
        ], true);

        return Inertia::render('Orders/PrintWorkflowDocumentPreview', [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'documentId' => $orderDocument->id,
            'documentTitle' => $orderDocument->original_name ?: 'Черновик заявки',
            'embedUrl' => route('orders.documents.download-draft', [$order, $orderDocument]).'?preview=1',
            'workflowStatusLabel' => $workflowStatus ? OrderDocumentWorkflowStatus::label($workflowStatus) : null,
            'canRequestApproval' => $canRequestApproval,
        ]);
    }

    public function downloadDraft(Request $request, Order $order, OrderDocument $orderDocument): Response|BinaryFileResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->file_path), 404);

        return $this->draftResponseBuilder->fromStoredDocx(
            $request,
            'local',
            $orderDocument->file_path,
            $orderDocument->original_name ?: 'draft.docx'
        );
    }

    public function downloadFinal(Request $request, Order $order, OrderDocument $orderDocument): BinaryFileResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->generated_pdf_path), 404);

        return Storage::disk('local')->download(
            $orderDocument->generated_pdf_path,
            'order-'.$order->id.'-document-'.$orderDocument->id.'.pdf'
        );
    }

    private function ensureDocumentBelongsToOrder(Order $order, OrderDocument $orderDocument): void
    {
        abort_unless((int) $orderDocument->order_id === (int) $order->id, 404);
    }

    private function ensureCanEditOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if (! $user->isManager()) {
            abort(403);
        }

        abort_unless((int) $order->manager_id === (int) $user->id, 403);
    }

    private function ensureCanApproveDocuments(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        abort_unless($user->isAdmin() || $user->isSupervisor(), 403);
    }

    private function ensureCanViewOrderDocuments(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if ($user->isManager() && (int) $order->manager_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    private function userCanManageOrderDocuments(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return $user->isManager() && (int) $order->manager_id === (int) $user->id;
    }
}
