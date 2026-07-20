<?php

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Services\DocumentStorageService;
use App\Services\DocxPdfPreviewService;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Services\OrderPrintFormDraftService;
use App\Services\Pdf\PdfVerificationQrStampService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class OrderPrintDocumentVerificationQrStampPolicyTest extends TestCase
{
    #[Test]
    public function without_docx_qr_does_not_stamp_pdf_by_default(): void
    {
        config(['documents.verification_qr.pdf_stamp_when_missing_in_docx' => false]);

        $stamp = $this->createMock(PdfVerificationQrStampService::class);
        $stamp->expects($this->never())->method('stamp');

        $service = new OrderPrintDocumentWorkflowService(
            $this->createMock(OrderPrintFormDraftService::class),
            $this->createMock(DocumentStorageService::class),
            $this->createMock(DocxPdfPreviewService::class),
            $stamp,
        );

        $document = new OrderDocument;
        $document->id = 97;
        $metadata = [
            'pdf_verification_qr_in_docx' => false,
            'pdf_verification_code' => 'ABCDEFGH12345678',
        ];

        $method = new ReflectionMethod(OrderPrintDocumentWorkflowService::class, 'stampVerificationQr');
        $result = $method->invokeArgs($service, ['%PDF-1.4 sample', $document, &$metadata]);

        $this->assertSame('%PDF-1.4 sample', $result);
        $this->assertArrayNotHasKey('pdf_verification_qr', $metadata);
    }

    #[Test]
    public function without_docx_qr_stamps_pdf_when_fallback_enabled(): void
    {
        config(['documents.verification_qr.pdf_stamp_when_missing_in_docx' => true]);

        $stamp = $this->createMock(PdfVerificationQrStampService::class);
        $stamp->expects($this->once())
            ->method('stamp')
            ->willReturn([
                'pdf' => '%PDF-1.4 stamped',
                'url' => 'https://example.test/verify',
                'code' => 'CODE123',
                'stamped_sha256' => str_repeat('a', 64),
            ]);

        $service = new OrderPrintDocumentWorkflowService(
            $this->createMock(OrderPrintFormDraftService::class),
            $this->createMock(DocumentStorageService::class),
            $this->createMock(DocxPdfPreviewService::class),
            $stamp,
        );

        $document = new OrderDocument;
        $document->id = 97;
        $metadata = ['pdf_verification_qr_in_docx' => false];

        $method = new ReflectionMethod(OrderPrintDocumentWorkflowService::class, 'stampVerificationQr');
        $result = $method->invokeArgs($service, ['%PDF-1.4 sample', $document, &$metadata]);

        $this->assertSame('%PDF-1.4 stamped', $result);
        $this->assertTrue((bool) ($metadata['pdf_verification_qr'] ?? false));
    }
}
