<?php

namespace Tests\Unit;

use App\Services\Pdf\PdfDocumentCertificationService;
use App\Support\Pdf\PdfSigningCertificateGenerator;
use TCPDF;
use Tests\TestCase;

class PdfDocumentCertificationServiceTest extends TestCase
{
    public function test_certify_adds_docmdp_signature_when_enabled(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('Расширение PHP openssl не установлено.');
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-pdf-signing-test-'.uniqid('', true);

        $paths = PdfSigningCertificateGenerator::generate($directory, 'CRM Test Signing', 30, true);

        config([
            'pdf_signing.enabled' => true,
            'pdf_signing.certificate_path' => $paths['certificate_path'],
            'pdf_signing.private_key_path' => $paths['private_key_path'],
            'pdf_signing.private_key_password' => '',
            'pdf_signing.docmdp' => 2,
            'pdf_signing.signer_name' => 'CRM Test',
        ]);

        $sourcePdf = $this->makeSamplePdf('Тестовая заявка');

        $result = (new PdfDocumentCertificationService)->certify($sourcePdf);

        $this->assertNotNull($result);
        $this->assertStringStartsWith('%PDF', $result['certified_pdf']);
        $this->assertSame(2, $result['docmdp']);
        $this->assertSame(64, strlen($result['sha256']));
        $this->assertStringContainsString('/ByteRange', $result['certified_pdf']);
        $this->assertStringContainsString('/DocMDP', $result['certified_pdf']);
        $this->assertStringContainsString('/Perms', $result['certified_pdf']);

        @unlink($paths['certificate_path']);
        @unlink($paths['private_key_path']);
        @rmdir($directory);
    }

    public function test_certify_returns_null_when_disabled(): void
    {
        config(['pdf_signing.enabled' => false]);

        $result = (new PdfDocumentCertificationService)->certify($this->makeSamplePdf('x'));

        $this->assertNull($result);
    }

    private function makeSamplePdf(string $title): string
    {
        $pdf = new TCPDF;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->Write(0, $title);

        return $pdf->Output('', 'S');
    }
}
