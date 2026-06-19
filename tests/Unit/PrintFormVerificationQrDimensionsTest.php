<?php

namespace Tests\Unit;

use App\Support\PrintFormVerificationQrDimensions;
use Tests\TestCase;

class PrintFormVerificationQrDimensionsTest extends TestCase
{
    public function test_defaults_are_smaller_than_legacy_hardcoded_sizes(): void
    {
        $this->assertSame(80, PrintFormVerificationQrDimensions::docxWidthPx());
        $this->assertSame(80, PrintFormVerificationQrDimensions::docxHeightPx());
        $this->assertSame(12.0, PrintFormVerificationQrDimensions::pdfStampSizeMm());
        $this->assertSame(5, PrintFormVerificationQrDimensions::pngPixelSize());
    }

    public function test_reads_values_from_config(): void
    {
        config()->set('documents.verification_qr', [
            'docx_px' => 88,
            'pdf_stamp_mm' => 12.5,
            'png_pixel_size' => 4,
        ]);

        $this->assertSame(88, PrintFormVerificationQrDimensions::docxWidthPx());
        $this->assertSame(12.5, PrintFormVerificationQrDimensions::pdfStampSizeMm());
        $this->assertSame(4, PrintFormVerificationQrDimensions::pngPixelSize());
    }

    public function test_enforces_minimum_sizes(): void
    {
        config()->set('documents.verification_qr', [
            'docx_px' => 10,
            'pdf_stamp_mm' => 2,
            'png_pixel_size' => 1,
        ]);

        $this->assertSame(48, PrintFormVerificationQrDimensions::docxWidthPx());
        $this->assertSame(8.0, PrintFormVerificationQrDimensions::pdfStampSizeMm());
        $this->assertSame(3, PrintFormVerificationQrDimensions::pngPixelSize());
    }
}
