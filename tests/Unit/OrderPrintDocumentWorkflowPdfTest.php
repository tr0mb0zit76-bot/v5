<?php

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Services\DocumentStorageService;
use App\Services\DocxPdfPreviewService;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Services\OrderPrintFormDraftService;
use App\Services\Pdf\PdfVerificationQrStampService;
use App\Support\OrderDocumentWorkflowStatus;
use Tests\TestCase;

class OrderPrintDocumentWorkflowPdfTest extends TestCase
{
    public function test_persist_generated_approved_pdf_writes_file_and_updates_document(): void
    {
        $document = $this->getMockBuilder(OrderDocument::class)
            ->onlyMethods(['update'])
            ->getMock();
        $document->order_id = 3;
        $document->metadata = ['storage_driver' => 'local'];

        $document->expects($this->once())
            ->method('update')
            ->with($this->callback(function (array $attributes): bool {
                return $attributes['generated_pdf_path'] === 'order_documents/3/request-order-3-approved.pdf'
                    && data_get($attributes, 'metadata.generated_pdf_storage_driver') === 'local';
            }))
            ->willReturn(true);

        $storage = $this->createMock(DocumentStorageService::class);
        $storage->method('configuredDriver')->willReturn(DocumentStorageService::DRIVER_LOCAL);
        $storage->expects($this->once())
            ->method('resolveOrderDocumentPath')
            ->with(3, 'request-order-3-approved.pdf')
            ->willReturn('order_documents/3/request-order-3-approved.pdf');
        $storage->expects($this->once())
            ->method('put')
            ->with(
                'order_documents/3/request-order-3-approved.pdf',
                '%PDF-1.4',
                DocumentStorageService::DRIVER_LOCAL,
            );

        $service = new OrderPrintDocumentWorkflowService(
            $this->createMock(OrderPrintFormDraftService::class),
            $storage,
            $this->createMock(DocxPdfPreviewService::class),
            $this->createMock(PdfVerificationQrStampService::class),
        );

        $service->persistGeneratedApprovedPdf($document, '%PDF-1.4', 'request-order-3-draft.docx');
    }

    public function test_ensure_approved_workflow_pdf_promotes_cached_preview_pdf(): void
    {
        $document = $this->getMockBuilder(OrderDocument::class)
            ->onlyMethods(['update', 'refresh'])
            ->getMock();
        $document->workflow_status = OrderDocumentWorkflowStatus::APPROVED;
        $document->order_id = 3;
        $document->original_name = 'request-order-3-draft.docx';
        $document->metadata = [
            'preview_pdf_path' => 'order_documents/3/request-order-3-preview.pdf',
            'preview_pdf_storage_driver' => 'local',
        ];

        $storage = $this->createMock(DocumentStorageService::class);
        $storage->method('exists')->willReturn(true);
        $storage->method('get')->willReturn('%PDF-preview');
        $storage->method('configuredDriver')->willReturn(DocumentStorageService::DRIVER_LOCAL);
        $storage->expects($this->once())
            ->method('resolveOrderDocumentPath')
            ->with(3, 'request-order-3-approved.pdf')
            ->willReturn('order_documents/3/request-order-3-approved.pdf');
        $storage->expects($this->once())
            ->method('put')
            ->with(
                'order_documents/3/request-order-3-approved.pdf',
                '%PDF-preview',
                DocumentStorageService::DRIVER_LOCAL,
            );

        $document->expects($this->once())->method('update')->willReturn(true);

        $preview = $this->createMock(DocxPdfPreviewService::class);
        $preview->expects($this->never())->method('convertToPdf');

        $service = new OrderPrintDocumentWorkflowService(
            $this->createMock(OrderPrintFormDraftService::class),
            $storage,
            $preview,
            $this->createMock(PdfVerificationQrStampService::class),
        );

        $service->ensureApprovedWorkflowPdf($document);
    }

    public function test_ensure_approved_workflow_pdf_skips_when_pdf_already_exists(): void
    {
        $preview = $this->createMock(DocxPdfPreviewService::class);
        $preview->expects($this->never())->method('convertToPdf');

        $service = new OrderPrintDocumentWorkflowService(
            $this->createMock(OrderPrintFormDraftService::class),
            $this->createMock(DocumentStorageService::class),
            $preview,
            $this->createMock(PdfVerificationQrStampService::class),
        );

        $document = new OrderDocument([
            'workflow_status' => OrderDocumentWorkflowStatus::APPROVED,
            'generated_pdf_path' => 'order_documents/1/exists.pdf',
            'file_path' => 'order_documents/1/signed.docx',
        ]);

        $service->ensureApprovedWorkflowPdf($document);
    }
}
