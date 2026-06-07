<?php

namespace Tests\Unit\Commercial;

use App\Services\Commercial\MailAttachmentPreviewService;
use App\Services\DocxPdfPreviewService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailAttachmentPreviewServiceTest extends TestCase
{
    #[Test]
    public function it_detects_pdf_and_image_preview_kinds(): void
    {
        $service = app(MailAttachmentPreviewService::class);

        $this->assertSame('pdf', $service->previewKind('scan.pdf'));
        $this->assertSame('image', $service->previewKind('photo.jpg', 'image/jpeg'));
    }

    #[Test]
    public function it_builds_inline_pdf_response_for_pdf_attachments(): void
    {
        $service = app(MailAttachmentPreviewService::class);
        $pdf = '%PDF-1.4 test';

        $response = $service->buildPreviewResponse($pdf, 'offer.pdf', 'application/pdf');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertSame($pdf, $response->getContent());
    }

    #[Test]
    public function it_uses_gotenberg_for_office_attachments_when_enabled(): void
    {
        config(['document_preview.driver' => 'gotenberg', 'document_preview.gotenberg.url' => 'http://127.0.0.1:3000']);

        $mock = $this->createMock(DocxPdfPreviewService::class);
        $mock->method('isEnabled')->willReturn(true);
        $mock->expects($this->once())
            ->method('convertOfficeDocumentToPdf')
            ->with('docx-bytes', 'offer.docx')
            ->willReturn('%PDF-1.4 converted');

        $service = new MailAttachmentPreviewService($mock);

        $this->assertSame('office', $service->previewKind('offer.docx'));

        $response = $service->buildPreviewResponse('docx-bytes', 'offer.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertSame('%PDF-1.4 converted', $response->getContent());
    }
}
