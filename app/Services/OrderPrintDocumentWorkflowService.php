<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Models\User;
use App\Services\Pdf\PdfDocumentCertificationService;
use App\Services\Pdf\PdfVerificationQrStampService;
use App\Support\OrderDocumentWorkflowStatus;
use App\Support\OrderPrintFormContext;
use App\Support\PrintFormVerificationCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderPrintDocumentWorkflowService
{
    public function __construct(
        private readonly OrderPrintFormDraftService $draftService,
        private readonly DocumentStorageService $documentStorage,
        private readonly DocxPdfPreviewService $docxPdfPreviewService,
        private readonly PdfVerificationQrStampService $pdfVerificationQrStamp,
    ) {}

    /**
     * Создаёт запись документа и сохраняет сгенерированный DOCX на диске.
     *
     * Порядок: сначала создаём запись OrderDocument (чтобы получить ID и код проверки),
     * затем генерируем DOCX с QR-кодом в контексте, обновляем запись готовым файлом.
     */
    public function createFromTemplate(
        Order $order,
        PrintFormTemplate $template,
        User $user,
        ?OrderPrintFormContext $context = null,
    ): OrderDocument {
        $order = $this->draftService->loadOrderContext($order);

        // Создаём временную запись OrderDocument, чтобы получить ID и код проверки.
        $document = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => $template->document_type,
            'template_id' => $template->id,
            'uploaded_by' => $user->id,
            'document_group' => $template->document_group,
            'source' => 'print_template',
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'status' => 'draft',
            'signature_status' => 'not_requested',
            'requires_counterparty_signature' => (bool) $template->requires_counterparty_signature,
            'file_size' => 0,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'metadata' => array_filter([
                'flow' => 'print_template_workflow',
                'party' => in_array($context?->printParty, ['customer', 'carrier'], true)
                    ? $context->printParty
                    : $this->resolveMetadataParty($template),
                'template_code' => $template->code,
                'template_name' => $template->name,
                'storage_driver' => $this->documentStorage->configuredDriver(),
                'order_leg_stage' => $context?->legStage,
                'carrier_contractor_id' => $context?->carrierContractorId,
                'carrier_slot' => $context?->carrierSlot,
                'route_legs_as_table_rows' => $context?->routeLegsAsTableRows ?? false,
            ], fn (mixed $value): bool => $value !== null && $value !== false && $value !== ''),
        ]);

        $verificationCode = PrintFormVerificationCode::forOrderDocument($document);
        $contextWithCode = $this->printContextWithVerificationCode(
            $this->printContextWithOrderDocumentId($context, (int) $document->id),
            $verificationCode,
        );

        $generated = $this->draftService->generate($template, $order, false, $contextWithCode);

        $permanentPath = $this->documentStorage->resolveOrderDocumentPath(
            $order->id,
            $generated['download_name'],
        );
        $docxContents = Storage::disk($generated['disk'])->get($generated['path']);
        $this->documentStorage->put($permanentPath, $docxContents);
        Storage::disk($generated['disk'])->delete($generated['path']);

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata['pdf_verification_code'] = $verificationCode;
        $metadata['pdf_verification_qr'] = true;
        $metadata['pdf_verification_qr_in_docx'] = (bool) ($generated['verification_qr_injected'] ?? false);

        $document->update([
            'original_name' => $generated['download_name'],
            'file_path' => $permanentPath,
            'file_size' => $this->documentStorage->size(
                $permanentPath,
                knownContents: $docxContents
            ),
            'metadata' => $metadata,
        ]);

        return $document->refresh();
    }

    /**
     * Создаёт новый контекст с кодом проверки, если исходный контекст есть;
     * иначе создаёт минимальный контекст только с кодом.
     */
    private function printContextWithOrderDocumentId(?OrderPrintFormContext $context, int $orderDocumentId): ?OrderPrintFormContext
    {
        if ($context === null) {
            return new OrderPrintFormContext(orderDocumentId: $orderDocumentId);
        }

        return new OrderPrintFormContext(
            legStage: $context->legStage,
            carrierContractorId: $context->carrierContractorId,
            routeLegsAsTableRows: $context->routeLegsAsTableRows,
            printParty: $context->printParty,
            carrierSlot: $context->carrierSlot,
            documentVerificationCode: $context->documentVerificationCode,
            orderDocumentId: $orderDocumentId,
        );
    }

    private function printContextWithVerificationCode(
        ?OrderPrintFormContext $context,
        string $verificationCode,
    ): ?OrderPrintFormContext {
        if ($context === null) {
            return new OrderPrintFormContext(
                documentVerificationCode: $verificationCode,
            );
        }

        return new OrderPrintFormContext(
            legStage: $context->legStage,
            carrierContractorId: $context->carrierContractorId,
            routeLegsAsTableRows: $context->routeLegsAsTableRows,
            printParty: $context->printParty,
            carrierSlot: $context->carrierSlot,
            documentVerificationCode: $verificationCode,
            orderDocumentId: $context->orderDocumentId,
        );
    }

    public function requestApproval(OrderDocument $document, User $user): void
    {
        $this->assertWorkflowDocument($document);

        if (! in_array($document->workflow_status, [
            OrderDocumentWorkflowStatus::DRAFT,
            OrderDocumentWorkflowStatus::REJECTED,
        ], true)) {
            throw new \InvalidArgumentException('Отправка на согласование доступна только для черновика или после отклонения.');
        }

        $document->update([
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'approval_requested_at' => now(),
            'approval_requested_by' => $user->id,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'approved_at' => null,
            'approved_by' => null,
            'status' => 'pending',
        ]);
    }

    public function approve(OrderDocument $document, User $user): void
    {
        $this->assertWorkflowDocument($document);

        if ($document->workflow_status !== OrderDocumentWorkflowStatus::PENDING_APPROVAL) {
            throw new \InvalidArgumentException('Согласовать можно только документ в статусе «На согласовании».');
        }

        $document->update([
            'workflow_status' => OrderDocumentWorkflowStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'status' => 'pending',
        ]);

        $document->refresh();
        $this->materializeSignedPrintArtifacts($document);
    }

    public function reject(OrderDocument $document, User $user, string $reason): void
    {
        $this->assertWorkflowDocument($document);

        if ($document->workflow_status !== OrderDocumentWorkflowStatus::PENDING_APPROVAL) {
            throw new \InvalidArgumentException('Отклонить можно только документ в статусе «На согласовании».');
        }

        $document->update([
            'workflow_status' => OrderDocumentWorkflowStatus::REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $user->id,
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by' => null,
            'status' => 'draft',
        ]);
    }

    /**
     * Прикрепляет финальный нередактируемый PDF после печати и подписи.
     */
    public function attachFinalPdf(OrderDocument $document, UploadedFile $file, User $user): void
    {
        $this->assertWorkflowDocument($document);

        if ($document->workflow_status !== OrderDocumentWorkflowStatus::APPROVED) {
            throw new \InvalidArgumentException('Загрузить финальный PDF можно только после согласования.');
        }

        if (filled($document->generated_pdf_path)) {
            $prevDriver = (string) data_get($document->metadata, 'generated_pdf_storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete((string) $document->generated_pdf_path, $prevDriver);
        }

        $path = $this->documentStorage->resolveOrderDocumentPath(
            (int) $document->order_id,
            $file->getClientOriginalName(),
        );
        $pdfContents = $file->getContent();
        $this->documentStorage->put($path, $pdfContents);

        $updates = [
            'generated_pdf_path' => $path,
            'workflow_status' => OrderDocumentWorkflowStatus::FINALIZED,
            'status' => 'signed',
            'signature_status' => 'signed_internal',
            'internal_signed_at' => now(),
            'internal_signed_by' => $user->id,
            'mime_type' => 'application/pdf',
            'file_size' => $file->getSize() ?: strlen($pdfContents),
            'original_name' => $file->getClientOriginalName(),
        ];

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata['generated_pdf_storage_driver'] = $this->documentStorage->configuredDriver();
        $updates['metadata'] = $metadata;

        $document->update($updates);
    }

    /**
     * Пересоздаёт DOCX из шаблона (черновик или отклонён).
     */
    public function regenerateDraft(OrderDocument $document, User $user): void
    {
        $this->assertWorkflowDocument($document);

        if (! in_array($document->workflow_status, [
            OrderDocumentWorkflowStatus::DRAFT,
            OrderDocumentWorkflowStatus::REJECTED,
        ], true)) {
            throw new \InvalidArgumentException('Пересоздать черновик можно только в статусе черновика или после отклонения.');
        }

        if ($document->template_id === null) {
            throw new \InvalidArgumentException('У документа не указан шаблон.');
        }

        $template = PrintFormTemplate::query()->findOrFail($document->template_id);
        $order = Order::query()->findOrFail($document->order_id);
        $order = $this->draftService->loadOrderContext($order);
        $generated = $this->draftService->generate(
            $template,
            $order,
            false,
            $this->printContextFromDocumentMetadata($document),
        );

        if ($document->file_path) {
            $storageDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($document->file_path, $storageDriver);
        }

        $permanentPath = $this->documentStorage->resolveOrderDocumentPath(
            $order->id,
            $generated['download_name'],
        );
        $docxContents = Storage::disk($generated['disk'])->get($generated['path']);
        $this->documentStorage->put($permanentPath, $docxContents);
        Storage::disk($generated['disk'])->delete($generated['path']);

        $updates = [
            'file_path' => $permanentPath,
            'uploaded_by' => $user->id,
            'file_size' => $this->documentStorage->size(
                $permanentPath,
                knownContents: $docxContents
            ),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata['storage_driver'] = $this->documentStorage->configuredDriver();
        $metadata['pdf_verification_qr_in_docx'] = (bool) ($generated['verification_qr_injected'] ?? false);
        $metadata = $this->withoutCachedBrowserPreviewPdf($document, $metadata);
        $updates['metadata'] = $metadata;

        $document->update($updates);
    }

    /**
     * После согласования руководителем: DOCX с печатью/подписью и PDF для отправки менеджером (если доступен Gotenberg).
     */
    private function materializeSignedPrintArtifacts(OrderDocument $document): void
    {
        if ($document->template_id === null) {
            return;
        }

        if ($document->workflow_status !== OrderDocumentWorkflowStatus::APPROVED) {
            return;
        }

        $template = PrintFormTemplate::query()->find($document->template_id);
        if ($template === null) {
            return;
        }

        $order = Order::query()->findOrFail($document->order_id);
        $order = $this->draftService->loadOrderContext($order);
        $generated = $this->draftService->generate(
            $template,
            $order,
            true,
            $this->printContextFromDocumentMetadata($document),
        );

        if ($document->file_path) {
            $storageDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($document->file_path, $storageDriver);
        }

        $signedFilename = $this->signedDocxFilename($generated['download_name']);
        $permanentPath = $this->documentStorage->resolveOrderDocumentPath($order->id, $signedFilename);
        $docxContents = Storage::disk($generated['disk'])->get($generated['path']);
        $this->documentStorage->put($permanentPath, $docxContents);
        Storage::disk($generated['disk'])->delete($generated['path']);

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata = $this->withoutCachedBrowserPreviewPdf($document, $metadata);
        $metadata['storage_driver'] = $this->documentStorage->configuredDriver();

        $pdfContents = $this->resolveApprovedWorkflowPdfContents(
            $document,
            $generated['download_name'],
            $docxContents,
        );
        if ($pdfContents === null) {
            Log::warning('order.print_workflow.approved_pdf_skipped', [
                'order_document_id' => $document->id,
                'message' => 'Конвертация DOCX→PDF недоступна после подписания.',
            ]);
        }

        $document->update([
            'file_path' => $permanentPath,
            'metadata' => $metadata,
            'file_size' => $this->documentStorage->size(
                $permanentPath,
                knownContents: $docxContents
            ),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'original_name' => $generated['download_name'],
        ]);
        $document->refresh();

        if ($pdfContents !== null) {
            $this->persistGeneratedApprovedPdf($document, $pdfContents, $generated['download_name']);
        }
    }

    /**
     * Для подписанных заявок без сохранённого PDF — пробует сконвертировать текущий DOCX (лениво, при открытии карточки/превью).
     */
    public function ensureApprovedWorkflowPdf(OrderDocument $document): void
    {
        if (! in_array($document->workflow_status, [
            OrderDocumentWorkflowStatus::APPROVED,
            OrderDocumentWorkflowStatus::FINALIZED,
        ], true)) {
            return;
        }

        if (filled($document->generated_pdf_path)) {
            return;
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $previewPath = (string) ($metadata['preview_pdf_path'] ?? '');
        $previewDriver = (string) ($metadata['preview_pdf_storage_driver'] ?? DocumentStorageService::DRIVER_LOCAL);

        if ($previewPath !== '' && $this->documentStorage->exists($previewPath, $previewDriver)) {
            $this->persistGeneratedApprovedPdf(
                $document,
                $this->documentStorage->get($previewPath, $previewDriver),
                $document->original_name ?: 'draft.docx',
            );

            return;
        }

        if (blank($document->file_path)) {
            return;
        }

        $storageDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
        $docxContents = $this->documentStorage->get((string) $document->file_path, $storageDriver);
        $downloadName = $document->original_name ?: 'draft.docx';
        $pdfContents = $this->resolveApprovedWorkflowPdfContents($document, $downloadName, $docxContents);

        if ($pdfContents === null) {
            return;
        }

        $this->persistGeneratedApprovedPdf($document, $pdfContents, $downloadName);
    }

    /**
     * LibreOffice/Gotenberg иногда не конвертирует DOCX после VML-патча позиций подписи/печати — тогда пересобираем без патча только для PDF.
     */
    private function resolveApprovedWorkflowPdfContents(
        OrderDocument $document,
        string $downloadName,
        string $docxContents,
    ): ?string {
        $pdfContents = $this->docxPdfPreviewService->convertToPdf($docxContents, $downloadName);
        if ($pdfContents !== null) {
            return $pdfContents;
        }

        if ($document->template_id === null) {
            return null;
        }

        $template = PrintFormTemplate::query()->find($document->template_id);
        if ($template === null) {
            return null;
        }

        $order = Order::query()->find($document->order_id);
        if ($order === null) {
            return null;
        }

        $order = $this->draftService->loadOrderContext($order);
        $generated = $this->draftService->generate(
            $template,
            $order,
            true,
            $this->printContextFromDocumentMetadata($document),
            applyVmlOverlayPatch: false,
        );

        try {
            $fallbackDocx = Storage::disk($generated['disk'])->get($generated['path']);

            return $this->docxPdfPreviewService->convertToPdf($fallbackDocx, $generated['download_name']);
        } finally {
            Storage::disk($generated['disk'])->delete($generated['path']);
        }
    }

    public function persistGeneratedApprovedPdf(OrderDocument $document, string $pdfContents, string $downloadName): void
    {
        if (filled($document->generated_pdf_path)) {
            return;
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $pdfContents = $this->stampVerificationQr($pdfContents, $document, $metadata);
        $pdfContents = $this->maybeCertifyApprovedPdf($pdfContents, $metadata);

        $orderId = (int) $document->order_id;
        $pdfPath = $this->documentStorage->resolveOrderDocumentPath(
            $orderId,
            $this->approvedPdfFilename($downloadName),
        );
        $driver = $this->documentStorage->configuredDriver();
        $this->documentStorage->put($pdfPath, $pdfContents, $driver);

        $metadata['generated_pdf_storage_driver'] = $driver;

        $document->update([
            'generated_pdf_path' => $pdfPath,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Наносит QR-штамп на PDF, если он ещё не был вставлен в DOCX через плейсхолдер.
     * Если QR уже есть в DOCX (признак pdf_verification_qr_in_docx), PDF-штамп не накладывается,
     * но URL для проверки сохраняется в metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function stampVerificationQr(string $pdfContents, OrderDocument $document, array &$metadata): string
    {
        if (! empty($metadata['pdf_verification_qr_in_docx'])) {
            $code = (string) ($metadata['pdf_verification_code'] ?? '');
            if ($code !== '') {
                $metadata['pdf_verification_url'] = route('print-verification.order-documents.show', [
                    'orderDocument' => $document->id,
                    'code' => $code,
                ]);
            }

            return $pdfContents;
        }

        $stamped = $this->pdfVerificationQrStamp->stamp($pdfContents, $document);
        if ($stamped === null) {
            return $pdfContents;
        }

        $metadata['pdf_verification_qr'] = true;
        $metadata['pdf_verification_url'] = $stamped['url'];
        $metadata['pdf_verification_code'] = $stamped['code'];
        $metadata['pdf_verification_stamped_sha256'] = $stamped['stamped_sha256'];

        return $stamped['pdf'];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function maybeCertifyApprovedPdf(string $pdfContents, array &$metadata): string
    {
        $certification = app(PdfDocumentCertificationService::class)->certify($pdfContents);
        if ($certification === null) {
            return $pdfContents;
        }

        $metadata['pdf_certified'] = true;
        $metadata['pdf_certified_sha256'] = $certification['sha256'];
        $metadata['pdf_certified_docmdp'] = $certification['docmdp'];
        $metadata['pdf_certified_at'] = now()->toIso8601String();

        return $certification['certified_pdf'];
    }

    /**
     * Удаляет закэшированный PDF предпросмотра в браузере — он привязан к старому DOCX и иначе остаётся в iframe.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function withoutCachedBrowserPreviewPdf(OrderDocument $document, array $metadata): array
    {
        $previewPath = (string) ($metadata['preview_pdf_path'] ?? '');
        if ($previewPath !== '') {
            $previewDriver = (string) ($metadata['preview_pdf_storage_driver'] ?? DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($previewPath, $previewDriver);
        }

        unset(
            $metadata['preview_pdf_path'],
            $metadata['preview_pdf_storage_driver'],
            $metadata['preview_pdf_generated_at'],
            $metadata['preview_pdf_source_docx_path'],
            $metadata['preview_pdf_source_docx_size'],
        );

        return $metadata;
    }

    /**
     * Удаляет из заказа документ по печатному шаблону до финального PDF: запись и файл черновика DOCX.
     */
    public function discardPrintWorkflowDocument(OrderDocument $document): void
    {
        $this->assertWorkflowDocument($document);

        if ($document->workflow_status === OrderDocumentWorkflowStatus::FINALIZED) {
            throw new \InvalidArgumentException('Нельзя удалить зафиксированный документ.');
        }

        if (($document->signature_status ?? '') === 'signed_both_sides') {
            throw new \InvalidArgumentException('Нельзя удалить документ после подписания с двух сторон.');
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $previewPath = (string) ($metadata['preview_pdf_path'] ?? '');
        if ($previewPath !== '') {
            $previewDriver = (string) ($metadata['preview_pdf_storage_driver'] ?? DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($previewPath, $previewDriver);
        }

        if (filled($document->generated_pdf_path)) {
            $pdfDriver = (string) data_get($document->metadata, 'generated_pdf_storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete((string) $document->generated_pdf_path, $pdfDriver);
        }

        if (filled($document->file_path)) {
            $storageDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($document->file_path, $storageDriver);
        }

        $document->delete();
    }

    private function assertWorkflowDocument(OrderDocument $document): void
    {
        if ($document->source === 'print_template') {
            return;
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];

        if (($metadata['flow'] ?? '') === 'print_template_workflow') {
            return;
        }

        throw new \InvalidArgumentException('Операция доступна только для документов из печатного шаблона.');
    }

    private function signedDocxFilename(string $downloadName): string
    {
        $lower = strtolower($downloadName);
        if (str_ends_with($lower, '-draft.docx')) {
            return substr($downloadName, 0, -strlen('-draft.docx')).'-signed.docx';
        }

        return $this->documentStorage->filenameWithVariant($downloadName, '-signed');
    }

    private function approvedPdfFilename(string $downloadName): string
    {
        $base = pathinfo($downloadName, PATHINFO_FILENAME);
        if (str_ends_with(strtolower($base), '-draft')) {
            $base = substr($base, 0, -strlen('-draft'));
        }

        return $base.'-approved.pdf';
    }

    private function resolveMetadataParty(PrintFormTemplate $template): string
    {
        $p = $template->party ?? null;
        if (is_string($p) && $p !== '' && in_array($p, ['customer', 'carrier', 'internal'], true)) {
            return $p;
        }

        if (in_array($template->document_type, ['request', 'contract_request'], true)) {
            return 'customer';
        }

        return 'internal';
    }

    /**
     * Восстанавливает контекст печатной формы из metadata документа.
     * Включает documentVerificationCode, чтобы при регенерации / materialize
     * QR-код подставлялся в DOCX и не дублировался на PDF.
     */
    private function printContextFromDocumentMetadata(OrderDocument $document): ?OrderPrintFormContext
    {
        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $legStage = isset($metadata['order_leg_stage']) && is_string($metadata['order_leg_stage'])
            ? trim($metadata['order_leg_stage'])
            : null;
        $carrierId = isset($metadata['carrier_contractor_id']) && (int) $metadata['carrier_contractor_id'] > 0
            ? (int) $metadata['carrier_contractor_id']
            : null;
        $carrierSlot = isset($metadata['carrier_slot']) && (int) $metadata['carrier_slot'] > 0
            ? (int) $metadata['carrier_slot']
            : null;
        $routeLegsAsTableRows = (bool) ($metadata['route_legs_as_table_rows'] ?? false);
        $verificationCode = isset($metadata['pdf_verification_code']) && is_string($metadata['pdf_verification_code'])
            ? trim($metadata['pdf_verification_code'])
            : null;

        if (($legStage === null || $legStage === '') && $carrierId === null && ! $routeLegsAsTableRows && ($verificationCode === null || $verificationCode === '')) {
            return null;
        }

        return new OrderPrintFormContext(
            legStage: $legStage !== '' ? $legStage : null,
            carrierContractorId: $carrierId,
            routeLegsAsTableRows: $routeLegsAsTableRows,
            carrierSlot: $carrierSlot,
            documentVerificationCode: ($verificationCode !== null && $verificationCode !== '') ? $verificationCode : null,
            orderDocumentId: (int) $document->id,
        );
    }
}
