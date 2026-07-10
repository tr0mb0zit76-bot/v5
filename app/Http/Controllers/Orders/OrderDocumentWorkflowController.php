<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendOrderDocumentMailRequest;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Rules\DocumentWithinPageBudget;
use App\Services\CabinetNotifier;
use App\Services\DocumentStorageService;
use App\Services\OrderCompensationService;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Services\Orders\OrderDocumentMailService;
use App\Services\PrintFormDraftResponseBuilder;
use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\DocumentPreview;
use App\Support\DocumentUploadBudget;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\OrderPrintFormContext;
use App\Support\OrderPrintWorkflowLock;
use App\Support\OrderViewAuthorization;
use Illuminate\Http\JsonResponse;
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
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function storeFromTemplate(Request $request, Order $order): RedirectResponse
    {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureCanManagePrintWorkflow($request);

        $validated = $request->validate([
            'print_form_template_id' => ['required', 'integer', 'exists:print_form_templates,id'],
            'print_party' => ['nullable', 'string', 'in:customer,carrier'],
            'order_leg_stage' => ['nullable', 'string', 'max:80'],
            'carrier_contractor_id' => ['nullable', 'integer', 'min:1'],
            'carrier_slot' => ['nullable', 'integer', 'min:1', 'max:9'],
            'route_legs_as_table_rows' => ['nullable', 'boolean'],
            'is_international_transport' => ['nullable', 'boolean'],
        ]);

        $template = PrintFormTemplate::query()->findOrFail($validated['print_form_template_id']);
        $order->loadMissing(['legs', 'legs.contractorAssignment']);

        $party = isset($validated['print_party']) && in_array($validated['print_party'], ['customer', 'carrier'], true)
            ? $validated['print_party']
            : null;
        $isInternationalTransport = array_key_exists('is_international_transport', $validated)
            ? (bool) $validated['is_international_transport']
            : null;

        abort_unless(
            $this->templateEligibility->isTemplateAvailableForOrder($template, $order, $party, $isInternationalTransport),
            422,
            'Шаблон недоступен для этого заказа. Проверьте тип перевозки (ВЭД), нашу компанию и перевозчика.'
        );

        $context = $this->resolvePrintFormContextFromRequest($validated);

        try {
            $this->workflowService->createFromTemplate($order, $template, $request->user(), $context);
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
        $this->ensureCanApproveDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        try {
            $this->workflowService->approve($orderDocument, $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $orderDocument->refresh();
        $this->cabinetNotifier->notifyDocumentApproved($order->fresh(), $orderDocument, $request->user());

        return redirect()
            ->to(route('orders.edit', $order).'?tab=documents')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Документ подписан: в файл добавлены печать и подпись, сформирован PDF для отправки контрагенту (если настроен конвертер DOCX→PDF).',
            ]);
    }

    public function reject(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse
    {
        $this->ensureCanApproveDocuments($request, $order);
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
            'pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:'.DocumentUploadBudget::absoluteMaxKilobytes(),
                new DocumentWithinPageBudget,
            ],
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

    public function discardPrintWorkflow(Request $request, Order $order, OrderDocument $orderDocument): RedirectResponse|JsonResponse
    {
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        $workflowStatus = $orderDocument->workflow_status;

        if ($workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL) {
            $user = $request->user();
            abort_if($user === null, 403);
            if ($user->hasSigningAuthority()) {
                $this->ensureCanApproveDocuments($request, $order);
                $this->ensureCanViewOrderDocuments($request, $order);
            } else {
                $this->ensureCanDiscardPendingApproval($request);
            }
        } elseif ($workflowStatus === OrderDocumentWorkflowStatus::APPROVED) {
            $this->ensureCanApproveDocuments($request, $order);
            $this->ensureCanViewOrderDocuments($request, $order);
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

        $message = 'Черновик по шаблону удалён из заказа.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()
            ->route('orders.edit', $order)
            ->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function previewDraft(Request $request, Order $order, OrderDocument $orderDocument): InertiaResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->file_path), 404);

        $this->workflowService->ensureApprovedWorkflowPdf($orderDocument);
        $orderDocument->refresh();

        $workflowStatus = $orderDocument->workflow_status;

        $canManage = $this->userCanManageOrderDocuments($request, $order);
        $canRequestApproval = $canManage && in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::DRAFT,
            OrderDocumentWorkflowStatus::REJECTED,
        ], true);

        $previewMeta = DocumentPreview::inertiaMeta();
        $template = $orderDocument->template_id
            ? PrintFormTemplate::query()->find($orderDocument->template_id)
            : null;

        $workflowShowsPrintImages = in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true);

        // Если уже есть сгенерированный PDF, браузерный предпросмотр отдаёт его целиком (download-draft + preview) —
        // подпись и печать уже внутри файла. HTML-слой с теми же PNG давал «двойные» печати/подписи.
        // Дополнительные PNG сверху оставляем только когда PDF финала ещё нет (например, конвертер недоступен),
        // а в DOCX остались только VML — тогда Gotenberg мог не отрисовать картинки в промежуточном PDF.
        $hasAuthoritativeWorkflowPdf = $workflowShowsPrintImages
            && filled($orderDocument->generated_pdf_path);

        $readonlyOverlays = $workflowShowsPrintImages
            && ! $hasAuthoritativeWorkflowPdf
            && $template instanceof PrintFormTemplate
            && $template->shouldApplyCrmOverlayOffsets();

        $signatureOverlayImageUrl = null;
        $stampOverlayImageUrl = null;
        if ($readonlyOverlays) {
            $settings = is_array($template->settings) ? $template->settings : [];
            if (filled(data_get($settings, 'image_overlays.internal_signature.path'))) {
                $signatureOverlayImageUrl = route('orders.documents.overlay-asset', [
                    $order,
                    $orderDocument,
                    'internal_signature',
                ]);
            }
            if (filled(data_get($settings, 'image_overlays.internal_stamp.path'))) {
                $stampOverlayImageUrl = route('orders.documents.overlay-asset', [
                    $order,
                    $orderDocument,
                    'internal_stamp',
                ]);
            }
        }

        $settings = $template instanceof PrintFormTemplate && is_array($template->settings) ? $template->settings : [];

        $canSign = $request->user()?->canSignDocumentsForOwnCompany($order->own_company_id) ?? false;
        $canWorkflowApprove = $canSign && $workflowStatus === OrderDocumentWorkflowStatus::PENDING_APPROVAL;
        $canWorkflowReject = $canWorkflowApprove;

        return Inertia::render('Orders/PrintWorkflowDocumentPreview', [
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'documentId' => $orderDocument->id,
            'documentTitle' => $orderDocument->original_name ?: 'Черновик заявки',
            'embedUrl' => route('orders.documents.download-draft', [$order, $orderDocument]).'?preview=1&preview_mode=browser',
            'workflowStatusLabel' => $workflowStatus ? OrderDocumentWorkflowStatus::label($workflowStatus) : null,
            'canRequestApproval' => $canRequestApproval,
            'canWorkflowApprove' => $canWorkflowApprove,
            'canWorkflowReject' => $canWorkflowReject,
            'workflowApproveUrl' => route('orders.documents.approve', [$order, $orderDocument]),
            'workflowRejectUrl' => route('orders.documents.reject', [$order, $orderDocument]),
            'canAdjustOverlay' => false,
            'overlaySaveUrl' => null,
            'documentPreview' => $previewMeta,
            'readonlyOverlayDecorations' => $readonlyOverlays && ($signatureOverlayImageUrl !== null || $stampOverlayImageUrl !== null),
            'signatureOverlayImageUrl' => $signatureOverlayImageUrl,
            'stampOverlayImageUrl' => $stampOverlayImageUrl,
            'signatureOffsetXmm' => (float) data_get($settings, 'image_overlays.internal_signature.offset_x_mm', 0),
            'signatureOffsetYmm' => (float) data_get($settings, 'image_overlays.internal_signature.offset_y_mm', 0),
            'stampOffsetXmm' => (float) data_get($settings, 'image_overlays.internal_stamp.offset_x_mm', 0),
            'stampOffsetYmm' => (float) data_get($settings, 'image_overlays.internal_stamp.offset_y_mm', 0),
            'signatureWidthMm' => (float) data_get($settings, 'image_overlays.internal_signature.width_mm', 42),
            'signatureHeightMm' => (float) data_get($settings, 'image_overlays.internal_signature.height_mm', 18),
            'stampWidthMm' => (float) data_get($settings, 'image_overlays.internal_stamp.width_mm', 30),
            'stampHeightMm' => (float) data_get($settings, 'image_overlays.internal_stamp.height_mm', 30),
            'finalPdfDownloadUrl' => filled($orderDocument->generated_pdf_path)
                ? route('orders.documents.download-final', [$order, $orderDocument])
                : null,
        ]);
    }

    public function previewUploaded(Request $request, Order $order, OrderDocument $orderDocument): Response|RedirectResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->file_path), 404);

        if ($this->documentIsPrintWorkflow($orderDocument)) {
            return redirect()->route('orders.documents.preview-draft', [$order, $orderDocument]);
        }

        $orderDocument->refresh();

        $driver = $this->resolveDraftStorageDriver($orderDocument);
        $contents = $this->documentStorage->get($orderDocument->file_path, $driver);

        $mime = (string) ($orderDocument->mime_type ?: 'application/octet-stream');
        $filename = $orderDocument->original_name ?: basename((string) $orderDocument->file_path);
        $disposition = $this->inlineDispositionForMime($mime, $filename);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=60',
            'Content-Disposition' => $disposition,
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

        $workflowStatus = $orderDocument->workflow_status;
        $revealed = in_array($workflowStatus, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true);
        $privileged = $request->user() !== null
            && ($request->user()->isAdmin() || $request->user()->isSupervisor());
        abort_unless($revealed || $privileged, 404);

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
        abort(403, 'Смещения подписи и печати настраиваются в «Настройки → Шаблоны», а не в карточке заказа.');
    }

    public function downloadDraft(Request $request, Order $order, OrderDocument $orderDocument): Response|BinaryFileResponse
    {
        $this->ensureCanViewOrderDocuments($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        abort_if(blank($orderDocument->file_path), 404);

        $orderDocument->refresh();

        $this->ensureMayDownloadPrintWorkflowDocx($request, $orderDocument);

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

        $this->workflowService->ensureApprovedWorkflowPdf($orderDocument);
        $orderDocument->refresh();

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

    public function sendByEmail(
        SendOrderDocumentMailRequest $request,
        Order $order,
        OrderDocument $orderDocument,
        OrderDocumentMailService $documentMail,
    ): RedirectResponse {
        $this->ensureCanEditOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $orderDocument);

        $this->workflowService->ensureApprovedWorkflowPdf($orderDocument);
        $orderDocument->refresh();

        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validated();

        $documentMail->sendSignedPdf(
            $user,
            $order,
            $orderDocument,
            $validated['to'],
            $validated['cc'] ?? [],
            $validated['subject'] ?? null,
            $validated['body'] ?? null,
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Документ отправлен по e-mail.',
        ]);
    }

    private function documentIsPrintWorkflow(OrderDocument $document): bool
    {
        if (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template') {
            return true;
        }

        return data_get($document->metadata, 'flow') === 'print_template_workflow';
    }

    private function ensureMayDownloadPrintWorkflowDocx(Request $request, OrderDocument $orderDocument): void
    {
        if (! $this->documentIsPrintWorkflow($orderDocument)) {
            return;
        }

        if (! in_array($orderDocument->workflow_status, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true)) {
            return;
        }

        if ($this->isBrowserPreviewRequested($request)) {
            return;
        }

        abort(403, 'Подписанный DOCX недоступен для скачивания. Используйте «Скачать PDF».');
    }

    private function inlineDispositionForMime(string $mime, string $filename): string
    {
        $asciiName = preg_replace('/[\r\n"]/', '', $filename) ?: 'file';
        $inline = str_starts_with($mime, 'image/')
            || $mime === 'application/pdf';
        $mode = $inline ? 'inline' : 'attachment';

        return sprintf('%s; filename="%s"', $mode, addcslashes($asciiName, '"\\'));
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

        if ($this->shouldInlineStoredWorkflowPdfForBrowserPreview($orderDocument)) {
            $pdfPath = (string) $orderDocument->generated_pdf_path;
            $pdfDriver = $this->resolveFinalPdfStorageDriver($orderDocument);
            if ($this->documentStorage->exists($pdfPath, $pdfDriver)) {
                $pdfContents = $this->documentStorage->get($pdfPath, $pdfDriver);

                return $this->inlinePdfResponse($pdfContents, $order, $orderDocument);
            }
        }

        $metadata = is_array($orderDocument->metadata) ? $orderDocument->metadata : [];
        $previewPath = (string) ($metadata['preview_pdf_path'] ?? '');
        $previewDriver = (string) ($metadata['preview_pdf_storage_driver'] ?? DocumentStorageService::DRIVER_LOCAL);
        $cachedSourceDocxPath = (string) ($metadata['preview_pdf_source_docx_path'] ?? '');
        $cachedSourceDocxSize = (int) ($metadata['preview_pdf_source_docx_size'] ?? -1);

        $currentDocxPath = (string) $orderDocument->file_path;
        $currentDocxSize = (int) ($orderDocument->file_size ?? -1);

        $cacheMatchesCurrentDraft = $cachedSourceDocxPath !== ''
            && $cachedSourceDocxPath === $currentDocxPath
            && ($cachedSourceDocxSize < 0 || $currentDocxSize < 0 || $cachedSourceDocxSize === $currentDocxSize);

        if ($previewPath !== '' && ! $cacheMatchesCurrentDraft) {
            $this->documentStorage->delete($previewPath, $previewDriver);
            unset(
                $metadata['preview_pdf_path'],
                $metadata['preview_pdf_storage_driver'],
                $metadata['preview_pdf_generated_at'],
                $metadata['preview_pdf_source_docx_path'],
                $metadata['preview_pdf_source_docx_size'],
            );
            $previewPath = '';
        }

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

        $sourceName = $orderDocument->original_name ?: 'draft.docx';
        $previewFilename = pathinfo($sourceName, PATHINFO_FILENAME).'-preview.pdf';
        $targetPath = $this->documentStorage->resolveOrderDocumentPath(
            (int) $orderDocument->order_id,
            $previewFilename,
        );
        $targetDriver = $this->documentStorage->configuredDriver();
        $this->documentStorage->put($targetPath, $pdfContents, $targetDriver);

        $fingerprintSize = $currentDocxSize >= 0 ? $currentDocxSize : strlen($docxContents);

        $metadata['preview_pdf_path'] = $targetPath;
        $metadata['preview_pdf_storage_driver'] = $targetDriver;
        $metadata['preview_pdf_generated_at'] = now()->toIso8601String();
        $metadata['preview_pdf_source_docx_path'] = $currentDocxPath;
        $metadata['preview_pdf_source_docx_size'] = $fingerprintSize;
        $orderDocument->update(['metadata' => $metadata]);

        if (in_array($orderDocument->workflow_status, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true) && blank($orderDocument->generated_pdf_path)) {
            $this->workflowService->persistGeneratedApprovedPdf(
                $orderDocument,
                $pdfContents,
                $orderDocument->original_name ?: 'draft.docx',
            );
            $orderDocument->refresh();
        }

        return $this->inlinePdfResponse($pdfContents, $order, $orderDocument);
    }

    private function shouldInlineStoredWorkflowPdfForBrowserPreview(OrderDocument $orderDocument): bool
    {
        $status = $orderDocument->workflow_status;
        if (! in_array($status, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true)) {
            return false;
        }

        return filled($orderDocument->generated_pdf_path);
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

        abort_unless(OrderViewAuthorization::userOwnsOrderRecord($order, (int) $user->id), 403);

        $order->loadMissing('documents');
        abort_if(OrderPrintWorkflowLock::allPrintWorkflowDocumentsFinalized($order), 403);
    }

    private function ensureCanApproveDocuments(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        abort_unless($user->canSignDocumentsForOwnCompany($order->own_company_id), 403);
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
        abort_unless(
            OrderDocumentAccessAuthorization::userMayViewDocuments($request->user(), $order),
            403,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvePrintFormContextFromRequest(array $validated): ?OrderPrintFormContext
    {
        $legStage = isset($validated['order_leg_stage']) && is_string($validated['order_leg_stage'])
            ? trim($validated['order_leg_stage'])
            : null;
        $carrierId = isset($validated['carrier_contractor_id']) ? (int) $validated['carrier_contractor_id'] : null;
        $carrierSlot = isset($validated['carrier_slot']) ? (int) $validated['carrier_slot'] : null;
        $routeLegsAsTableRows = (bool) ($validated['route_legs_as_table_rows'] ?? false);
        $printParty = isset($validated['print_party']) && in_array($validated['print_party'], ['customer', 'carrier'], true)
            ? $validated['print_party']
            : null;

        if (($legStage === null || $legStage === '')
            && ($carrierId === null || $carrierId <= 0)
            && ! $routeLegsAsTableRows
            && $printParty === null) {
            return null;
        }

        return new OrderPrintFormContext(
            legStage: $legStage !== '' ? $legStage : null,
            carrierContractorId: $carrierId > 0 ? $carrierId : null,
            routeLegsAsTableRows: $routeLegsAsTableRows,
            printParty: $printParty,
            carrierSlot: $carrierSlot > 0 ? $carrierSlot : null,
        );
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

        return $user->isManager() && OrderViewAuthorization::userOwnsOrderRecord($order, (int) $user->id);
    }
}
