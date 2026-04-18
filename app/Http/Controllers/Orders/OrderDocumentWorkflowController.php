<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Services\CabinetNotifier;
use App\Services\DocumentStorageService;
use App\Services\OrderCompensationService;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\OrderPrintWorkflowLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function storeFromTemplate(Request $request, Order $order): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureCanManagePrintWorkflow($request);

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
        $this->ensureCanManagePrintWorkflow($request);
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
        $this->ensureCanManagePrintWorkflow($request);
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
            ->with('flash', [
                'type' => 'success',
                'message' => 'Финальный PDF сохранён в папке заказа в хранилище документов и прикреплён к карточке. Скачать можно по ссылке «Скачать финальный PDF».',
            ]);
    }

    public function regenerateDraft(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureCanManagePrintWorkflow($request);
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

    public function discardPrintWorkflow(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        $workflowStatus = Schema::hasColumn('order_documents', 'workflow_status')
            ? $orderDocument->workflow_status
            : null;

        if ($workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL) {
            $this->ensureCanApproveDocuments($request);
            $this->ensureCanDiscardPendingApproval($request);
        } else {
            $this->ensureCanEditOrder($request, $order);
            $this->ensureCanManagePrintWorkflow($request);
        }

        try {
            $this->workflowService->discardPrintWorkflowDocument($orderDocument);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $this->orderCompensationService->recalculateImpactedPeriods($order);

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => 'Черновик по шаблону удалён из заказа.']);
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
        $template = $orderDocument->template_id !== null
            ? PrintFormTemplate::query()->find($orderDocument->template_id)
            : null;
        $templateSettings = is_array($template?->settings) ? $template->settings : [];
        $signaturePath = data_get($templateSettings, 'image_overlays.internal_signature.path');
        $stampPath = data_get($templateSettings, 'image_overlays.internal_stamp.path');
        $canAdjustOverlay = $canManage
            && $template !== null
            && $template->shouldApplyCrmOverlayOffsets()
            && in_array($workflowStatus, [OrderDocumentWorkflowStatus::DRAFT, OrderDocumentWorkflowStatus::REJECTED], true);

        return Inertia::render('Orders/PrintWorkflowDocumentPreview', [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'documentId' => $orderDocument->id,
            'documentTitle' => $orderDocument->original_name ?: 'Черновик заявки',
            'embedUrl' => route('orders.documents.download-draft', [$order, $orderDocument]).'?preview=1&preview_mode=browser',
            'workflowStatusLabel' => $workflowStatus ? OrderDocumentWorkflowStatus::label($workflowStatus) : null,
            'canRequestApproval' => $canRequestApproval,
            'canAdjustOverlay' => $canAdjustOverlay,
            'overlaySaveUrl' => $canAdjustOverlay ? route('orders.documents.update-overlay-positions', [$order, $orderDocument]) : null,
            'signatureOverlayImageUrl' => is_string($signaturePath) && $signaturePath !== ''
                ? route('orders.documents.overlay-asset', [$order, $orderDocument, 'overlayKey' => 'internal_signature'])
                : null,
            'stampOverlayImageUrl' => is_string($stampPath) && $stampPath !== ''
                ? route('orders.documents.overlay-asset', [$order, $orderDocument, 'overlayKey' => 'internal_stamp'])
                : null,
            'signatureOffsetXmm' => (float) data_get($templateSettings, 'image_overlays.internal_signature.offset_x_mm', 0),
            'signatureOffsetYmm' => (float) data_get($templateSettings, 'image_overlays.internal_signature.offset_y_mm', 0),
            'stampOffsetXmm' => (float) data_get($templateSettings, 'image_overlays.internal_stamp.offset_x_mm', 0),
            'stampOffsetYmm' => (float) data_get($templateSettings, 'image_overlays.internal_stamp.offset_y_mm', 0),
            'signatureWidthMm' => (float) data_get($templateSettings, 'image_overlays.internal_signature.width_mm', 42),
            'signatureHeightMm' => (float) data_get($templateSettings, 'image_overlays.internal_signature.height_mm', 18),
            'stampWidthMm' => (float) data_get($templateSettings, 'image_overlays.internal_stamp.width_mm', 30),
            'stampHeightMm' => (float) data_get($templateSettings, 'image_overlays.internal_stamp.height_mm', 30),
        ]);
    }

    public function overlayAsset(
        Request $request,
        Order $order,
        OrderDocument $orderDocument,
        string $overlayKey,
    ): Response {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);
        abort_unless(in_array($overlayKey, ['internal_signature', 'internal_stamp'], true), 404);
        abort_if($orderDocument->template_id === null, 404);

        $template = PrintFormTemplate::query()->findOrFail($orderDocument->template_id);
        $path = data_get($template->settings, 'image_overlays.'.$overlayKey.'.path');
        $disk = (string) data_get($template->settings, 'image_overlays.'.$overlayKey.'.disk', 'local');

        abort_if(! is_string($path) || $path === '' || ! Storage::disk($disk)->exists($path), 404);

        $contents = Storage::disk($disk)->get($path);
        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=60',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function updateOverlayPositions(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureCanManagePrintWorkflow($request);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);
        abort_if($orderDocument->template_id === null, 422, 'У документа не указан шаблон.');

        $validated = $request->validate([
            'signature_offset_x_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'signature_offset_y_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'stamp_offset_x_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
            'stamp_offset_y_mm' => ['required', 'numeric', 'min:-200', 'max:200'],
        ]);

        $template = PrintFormTemplate::query()->findOrFail($orderDocument->template_id);
        $settings = is_array($template->settings) ? $template->settings : [];
        $overlays = is_array($settings['image_overlays'] ?? null) ? $settings['image_overlays'] : [];
        $signature = is_array($overlays['internal_signature'] ?? null) ? $overlays['internal_signature'] : [];
        $stamp = is_array($overlays['internal_stamp'] ?? null) ? $overlays['internal_stamp'] : [];

        $signature['offset_x_mm'] = (float) $validated['signature_offset_x_mm'];
        $signature['offset_y_mm'] = (float) $validated['signature_offset_y_mm'];
        $stamp['offset_x_mm'] = (float) $validated['stamp_offset_x_mm'];
        $stamp['offset_y_mm'] = (float) $validated['stamp_offset_y_mm'];
        $overlays['internal_signature'] = $signature;
        $overlays['internal_stamp'] = $stamp;
        $settings['image_overlays'] = $overlays;

        $template->forceFill([
            'settings' => $settings,
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->clearCachedPreviewPdf($orderDocument);

        try {
            $this->workflowService->regenerateDraft($orderDocument->fresh(), $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()->route('orders.documents.preview-draft', [$order, $orderDocument]);
    }

    public function downloadDraft(Request $request, Order $order, OrderDocument $orderDocument): Response|BinaryFileResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->file_path), 404);

        $cachedPreviewResponse = $this->resolveCachedPdfPreviewResponse($request, $order, $orderDocument);
        if ($cachedPreviewResponse !== null) {
            return $cachedPreviewResponse;
        }

        $storageDriver = $this->resolveDraftStorageDriver($orderDocument);
        if ($storageDriver === DocumentStorageService::DRIVER_NEXTCLOUD) {
            $contents = $this->documentStorage->get($orderDocument->file_path, $storageDriver);

            return $this->draftResponseBuilder->fromStoredDocxContent(
                $request,
                $contents,
                $orderDocument->original_name ?: 'draft.docx'
            );
        }

        return $this->draftResponseBuilder->fromStoredDocx(
            $request,
            'local',
            $orderDocument->file_path,
            $orderDocument->original_name ?: 'draft.docx'
        );
    }

    public function downloadFinal(Request $request, Order $order, OrderDocument $orderDocument): Response|BinaryFileResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->generated_pdf_path), 404);

        $storageDriver = $this->resolveFinalPdfStorageDriver($orderDocument);
        if ($storageDriver === DocumentStorageService::DRIVER_NEXTCLOUD) {
            $contents = $this->documentStorage->get($orderDocument->generated_pdf_path, $storageDriver);

            return response()->streamDownload(
                static function () use ($contents): void {
                    echo $contents;
                },
                'order-'.$order->id.'-document-'.$orderDocument->id.'.pdf',
                [
                    'Content-Type' => 'application/pdf',
                    'Cache-Control' => 'no-store, private',
                ]
            );
        }

        return Storage::disk('local')->download(
            $orderDocument->generated_pdf_path,
            'order-'.$order->id.'-document-'.$orderDocument->id.'.pdf'
        );
    }

    private function resolveDraftStorageDriver(OrderDocument $orderDocument): string
    {
        $driver = data_get($orderDocument->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);

        return $driver === DocumentStorageService::DRIVER_NEXTCLOUD
            ? DocumentStorageService::DRIVER_NEXTCLOUD
            : DocumentStorageService::DRIVER_LOCAL;
    }

    private function resolveFinalPdfStorageDriver(OrderDocument $orderDocument): string
    {
        $driver = data_get($orderDocument->metadata, 'generated_pdf_storage_driver', DocumentStorageService::DRIVER_LOCAL);

        return $driver === DocumentStorageService::DRIVER_NEXTCLOUD
            ? DocumentStorageService::DRIVER_NEXTCLOUD
            : DocumentStorageService::DRIVER_LOCAL;
    }

    private function resolveCachedPdfPreviewResponse(
        Request $request,
        Order $order,
        OrderDocument $orderDocument,
    ): ?Response {
        if (! $this->isBrowserPreviewRequested($request)) {
            return null;
        }

        $metadata = is_array($orderDocument->metadata) ? $orderDocument->metadata : [];
        $previewPath = (string) ($metadata['preview_pdf_path'] ?? '');
        $previewDriver = (string) ($metadata['preview_pdf_storage_driver'] ?? DocumentStorageService::DRIVER_LOCAL);

        if ($previewPath !== '' && $this->documentStorage->exists($previewPath, $previewDriver)) {
            $pdfContents = $this->documentStorage->get($previewPath, $previewDriver);

            return $this->inlinePdfResponse($pdfContents, $order, $orderDocument);
        }

        $docxDriver = $this->resolveDraftStorageDriver($orderDocument);
        $docxContents = $this->documentStorage->get($orderDocument->file_path, $docxDriver);
        $pdfContents = $this->draftResponseBuilder->previewPdfFromDocxContent(
            $docxContents,
            $orderDocument->original_name ?: 'draft.docx'
        );

        if ($pdfContents === null) {
            return null;
        }

        $targetPath = sprintf(
            'order_documents/%d/%s-preview.pdf',
            (int) $orderDocument->order_id,
            (string) Str::uuid()
        );
        $targetDriver = $this->documentStorage->configuredDriver();
        $this->documentStorage->put($targetPath, $pdfContents, $targetDriver);

        $metadata['preview_pdf_path'] = $targetPath;
        $metadata['preview_pdf_storage_driver'] = $targetDriver;
        $metadata['preview_pdf_generated_at'] = now()->toIso8601String();
        $orderDocument->update(['metadata' => $metadata]);

        return $this->inlinePdfResponse($pdfContents, $order, $orderDocument);
    }

    private function inlinePdfResponse(string $contents, Order $order, OrderDocument $orderDocument): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'inline; filename="%s"',
                'order-'.$order->id.'-document-'.$orderDocument->id.'-preview.pdf'
            ),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function isBrowserPreviewRequested(Request $request): bool
    {
        return $request->boolean('preview')
            && strtolower($request->query('preview_mode', '')) === 'browser';
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

        $order->loadMissing('documents');
        abort_if(OrderPrintWorkflowLock::allPrintWorkflowDocumentsFinalized($order), 403);
    }

    private function ensureCanApproveDocuments(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        abort_unless($user->hasSigningAuthority(), 403);
    }

    private function ensureCanManagePrintWorkflow(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        abort_unless(! $user->hasSigningAuthority(), 403);
    }

    private function ensureCanDiscardPendingApproval(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        abort(403);
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

        if ($user->hasSigningAuthority()) {
            return false;
        }

        return $user->isManager() && (int) $order->manager_id === (int) $user->id;
    }

    private function clearCachedPreviewPdf(OrderDocument $orderDocument): void
    {
        $metadata = is_array($orderDocument->metadata) ? $orderDocument->metadata : [];
        $previewPath = (string) ($metadata['preview_pdf_path'] ?? '');
        $previewDriver = (string) ($metadata['preview_pdf_storage_driver'] ?? DocumentStorageService::DRIVER_LOCAL);

        if ($previewPath !== '' && $this->documentStorage->exists($previewPath, $previewDriver)) {
            $this->documentStorage->delete($previewPath, $previewDriver);
        }

        unset($metadata['preview_pdf_path'], $metadata['preview_pdf_storage_driver'], $metadata['preview_pdf_generated_at']);

        $orderDocument->update([
            'metadata' => $metadata,
        ]);
    }
}
