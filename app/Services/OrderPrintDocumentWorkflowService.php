<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PrintFormTemplate;
use App\Models\User;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderPrintDocumentWorkflowService
{
    public function __construct(
        private readonly OrderPrintFormDraftService $draftService,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * Создаёт запись документа и сохраняет сгенерированный DOCX на диске.
     */
    public function createFromTemplate(Order $order, PrintFormTemplate $template, User $user): OrderDocument
    {
        $order = $this->draftService->loadOrderContext($order);
        $generated = $this->draftService->generate($template, $order);

        $permanentPath = sprintf('order_documents/%d/%s-draft.docx', $order->id, (string) Str::uuid());
        $docxContents = Storage::disk($generated['disk'])->get($generated['path']);
        $this->documentStorage->put($permanentPath, $docxContents);
        Storage::disk($generated['disk'])->delete($generated['path']);

        $attributes = [
            'order_id' => $order->id,
            'type' => $template->document_type,
            'original_name' => $generated['download_name'],
            'file_path' => $permanentPath,
            'template_id' => $template->id,
            'uploaded_by' => $user->id,
            'metadata' => [
                'flow' => 'print_template_workflow',
                'party' => $this->resolveMetadataParty($template),
                'template_code' => $template->code,
                'template_name' => $template->name,
                'storage_driver' => $this->documentStorage->configuredDriver(),
            ],
        ];

        if (Schema::hasColumn('order_documents', 'document_group')) {
            $attributes['document_group'] = $template->document_group;
        }

        if (Schema::hasColumn('order_documents', 'source')) {
            $attributes['source'] = 'print_template';
        }

        if (Schema::hasColumn('order_documents', 'workflow_status')) {
            $attributes['workflow_status'] = OrderDocumentWorkflowStatus::DRAFT;
        }

        if (Schema::hasColumn('order_documents', 'status')) {
            $attributes['status'] = 'draft';
        }

        if (Schema::hasColumn('order_documents', 'signature_status')) {
            $attributes['signature_status'] = 'not_requested';
        }

        if (Schema::hasColumn('order_documents', 'requires_counterparty_signature')) {
            $attributes['requires_counterparty_signature'] = (bool) $template->requires_counterparty_signature;
        }

        if (Schema::hasColumn('order_documents', 'file_size')) {
            $attributes['file_size'] = $this->documentStorage->size(
                $permanentPath,
                knownContents: $docxContents
            );
        }

        if (Schema::hasColumn('order_documents', 'mime_type')) {
            $attributes['mime_type'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        /** @var OrderDocument $document */
        $document = OrderDocument::query()->create($attributes);

        return $document;
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

        $updates = [];

        if (Schema::hasColumn('order_documents', 'workflow_status')) {
            $updates['workflow_status'] = OrderDocumentWorkflowStatus::PENDING_APPROVAL;
        }

        if (Schema::hasColumn('order_documents', 'approval_requested_at')) {
            $updates['approval_requested_at'] = now();
        }

        if (Schema::hasColumn('order_documents', 'approval_requested_by')) {
            $updates['approval_requested_by'] = $user->id;
        }

        if (Schema::hasColumn('order_documents', 'rejected_at')) {
            $updates['rejected_at'] = null;
        }

        if (Schema::hasColumn('order_documents', 'rejected_by')) {
            $updates['rejected_by'] = null;
        }

        if (Schema::hasColumn('order_documents', 'rejection_reason')) {
            $updates['rejection_reason'] = null;
        }

        if (Schema::hasColumn('order_documents', 'approved_at')) {
            $updates['approved_at'] = null;
        }

        if (Schema::hasColumn('order_documents', 'approved_by')) {
            $updates['approved_by'] = null;
        }

        if (Schema::hasColumn('order_documents', 'status')) {
            $updates['status'] = 'pending';
        }

        $document->update($updates);
    }

    public function approve(OrderDocument $document, User $user): void
    {
        $this->assertWorkflowDocument($document);

        if ($document->workflow_status !== OrderDocumentWorkflowStatus::PENDING_APPROVAL) {
            throw new \InvalidArgumentException('Согласовать можно только документ в статусе «На согласовании».');
        }

        $updates = [];

        if (Schema::hasColumn('order_documents', 'workflow_status')) {
            $updates['workflow_status'] = OrderDocumentWorkflowStatus::APPROVED;
        }

        if (Schema::hasColumn('order_documents', 'approved_at')) {
            $updates['approved_at'] = now();
        }

        if (Schema::hasColumn('order_documents', 'approved_by')) {
            $updates['approved_by'] = $user->id;
        }

        if (Schema::hasColumn('order_documents', 'status')) {
            $updates['status'] = 'pending';
        }

        $document->update($updates);
    }

    public function reject(OrderDocument $document, User $user, string $reason): void
    {
        $this->assertWorkflowDocument($document);

        if ($document->workflow_status !== OrderDocumentWorkflowStatus::PENDING_APPROVAL) {
            throw new \InvalidArgumentException('Отклонить можно только документ в статусе «На согласовании».');
        }

        $updates = [];

        if (Schema::hasColumn('order_documents', 'workflow_status')) {
            $updates['workflow_status'] = OrderDocumentWorkflowStatus::REJECTED;
        }

        if (Schema::hasColumn('order_documents', 'rejected_at')) {
            $updates['rejected_at'] = now();
        }

        if (Schema::hasColumn('order_documents', 'rejected_by')) {
            $updates['rejected_by'] = $user->id;
        }

        if (Schema::hasColumn('order_documents', 'rejection_reason')) {
            $updates['rejection_reason'] = $reason;
        }

        if (Schema::hasColumn('order_documents', 'approved_at')) {
            $updates['approved_at'] = null;
        }

        if (Schema::hasColumn('order_documents', 'approved_by')) {
            $updates['approved_by'] = null;
        }

        if (Schema::hasColumn('order_documents', 'status')) {
            $updates['status'] = 'draft';
        }

        $document->update($updates);
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

        $path = sprintf('order_documents/%d/%s-final.pdf', $document->order_id, (string) Str::uuid());
        $pdfContents = $file->getContent();
        $this->documentStorage->put($path, $pdfContents);

        $updates = [
            'generated_pdf_path' => $path,
        ];

        if (Schema::hasColumn('order_documents', 'workflow_status')) {
            $updates['workflow_status'] = OrderDocumentWorkflowStatus::FINALIZED;
        }

        if (Schema::hasColumn('order_documents', 'status')) {
            $updates['status'] = 'signed';
        }

        if (Schema::hasColumn('order_documents', 'signature_status')) {
            $updates['signature_status'] = 'signed_internal';
        }

        if (Schema::hasColumn('order_documents', 'internal_signed_at')) {
            $updates['internal_signed_at'] = now();
        }

        if (Schema::hasColumn('order_documents', 'internal_signed_by')) {
            $updates['internal_signed_by'] = $user->id;
        }

        if (Schema::hasColumn('order_documents', 'mime_type')) {
            $updates['mime_type'] = 'application/pdf';
        }

        if (Schema::hasColumn('order_documents', 'file_size')) {
            $updates['file_size'] = $file->getSize() ?: strlen($pdfContents);
        }

        if (Schema::hasColumn('order_documents', 'original_name')) {
            $updates['original_name'] = $file->getClientOriginalName();
        }

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
        $generated = $this->draftService->generate($template, $order);

        if ($document->file_path) {
            $storageDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($document->file_path, $storageDriver);
        }

        $permanentPath = sprintf('order_documents/%d/%s-draft.docx', $order->id, (string) Str::uuid());
        $docxContents = Storage::disk($generated['disk'])->get($generated['path']);
        $this->documentStorage->put($permanentPath, $docxContents);
        Storage::disk($generated['disk'])->delete($generated['path']);

        $updates = [
            'file_path' => $permanentPath,
            'uploaded_by' => $user->id,
        ];

        if (Schema::hasColumn('order_documents', 'file_size')) {
            $updates['file_size'] = $this->documentStorage->size(
                $permanentPath,
                knownContents: $docxContents
            );
        }

        if (Schema::hasColumn('order_documents', 'mime_type')) {
            $updates['mime_type'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $metadata['storage_driver'] = $this->documentStorage->configuredDriver();
        $updates['metadata'] = $metadata;

        $document->update($updates);
    }

    /**
     * Удаляет из заказа документ по печатному шаблону до финального PDF: запись и файл черновика DOCX.
     */
    public function discardPrintWorkflowDocument(OrderDocument $document): void
    {
        $this->assertWorkflowDocument($document);

        if (filled($document->generated_pdf_path)) {
            throw new \InvalidArgumentException('Нельзя удалить документ с прикреплённым финальным PDF.');
        }

        if (Schema::hasColumn('order_documents', 'workflow_status')
            && $document->workflow_status === OrderDocumentWorkflowStatus::FINALIZED) {
            throw new \InvalidArgumentException('Нельзя удалить зафиксированный документ.');
        }

        if (filled($document->file_path)) {
            $storageDriver = (string) data_get($document->metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->delete($document->file_path, $storageDriver);
        }

        $document->delete();
    }

    private function assertWorkflowDocument(OrderDocument $document): void
    {
        if (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template') {
            return;
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];

        if (($metadata['flow'] ?? '') === 'print_template_workflow') {
            return;
        }

        throw new \InvalidArgumentException('Операция доступна только для документов из печатного шаблона.');
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
}
