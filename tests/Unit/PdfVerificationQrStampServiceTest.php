<?php

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Services\Pdf\PdfVerificationQrStampService;
use Illuminate\Support\Carbon;
use TCPDF;
use Tests\TestCase;

class PdfVerificationQrStampServiceTest extends TestCase
{
    public function test_stamp_adds_verification_qr_and_code_to_pdf(): void
    {
        $document = new OrderDocument([
            'order_id' => 123,
        ]);
        $document->id = 456;
        $document->created_at = Carbon::parse('2026-06-12 10:00:00');

        $result = app(PdfVerificationQrStampService::class)->stamp($this->makeSamplePdf(), $document);

        $this->assertNotNull($result);
        $this->assertStringStartsWith('%PDF', $result['pdf']);
        $this->assertSame(16, strlen($result['code']));
        $this->assertSame(64, strlen($result['stamped_sha256']));
        $this->assertStringContainsString('/Subtype /Image', $result['pdf']);
        $this->assertStringContainsString('verify/order-documents/456', $result['url']);
    }

    private function makeSamplePdf(): string
    {
        $pdf = new TCPDF;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->Write(0, 'Test order document');

        return $pdf->Output('', 'S');
    }
}
