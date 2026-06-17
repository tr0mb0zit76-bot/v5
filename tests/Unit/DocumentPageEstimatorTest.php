<?php

namespace Tests\Unit;

use App\Support\DocumentPageEstimator;
use App\Support\DocumentUploadBudget;
use Illuminate\Http\UploadedFile;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class DocumentPageEstimatorTest extends TestCase
{
    protected function tearDown(): void
    {
        if (isset($this->tempPdfPath) && is_string($this->tempPdfPath) && is_file($this->tempPdfPath)) {
            @unlink($this->tempPdfPath);
        }

        parent::tearDown();
    }

    private string $tempPdfPath;

    public function test_estimate_counts_two_page_pdf_via_fpdi(): void
    {
        $this->tempPdfPath = $this->createPdfWithPages(2);

        $pages = DocumentPageEstimator::estimatePath($this->tempPdfPath, 'pdf');

        $this->assertSame(2, $pages);
    }

    public function test_two_page_pdf_allows_one_mebibyte_upload_budget(): void
    {
        config(['documents.bytes_per_page' => 600 * 1024]);

        $this->tempPdfPath = $this->createPdfWithPages(2);
        $uploaded = new UploadedFile($this->tempPdfPath, 'two-pages.pdf', 'application/pdf', null, true);

        $maxBytes = DocumentUploadBudget::maxBytes($uploaded);

        $this->assertGreaterThanOrEqual(1_048_576, $maxBytes);
    }

    public function test_blob_fallback_reads_count_from_pages_tree(): void
    {
        $blob = <<<'PDF'
%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Count 2/Kids[3 0 R 4 0 R]>>endobj
PDF;

        $method = new \ReflectionMethod(DocumentPageEstimator::class, 'estimatePdfFromBlob');
        $method->setAccessible(true);

        /** @var int $pages */
        $pages = $method->invoke(null, $blob);

        $this->assertSame(2, $pages);
    }

    private function createPdfWithPages(int $pageCount): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-pdf-est-');
        if ($path === false) {
            $this->fail('Не удалось создать временный PDF.');
        }

        $pdfPath = $path.'.pdf';
        @unlink($path);

        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        for ($i = 0; $i < $pageCount; $i++) {
            $pdf->AddPage();
        }

        $pdf->Output($pdfPath, 'F');

        return $pdfPath;
    }
}
