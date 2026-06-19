<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Размеры QR проверки подлинности в печатных формах (DOCX и PDF-штамп).
 */
final class PrintFormVerificationQrDimensions
{
    public static function docxWidthPx(): int
    {
        return max(48, (int) config('documents.verification_qr.docx_px', 80));
    }

    public static function docxHeightPx(): int
    {
        return self::docxWidthPx();
    }

    public static function pdfStampSizeMm(): float
    {
        return max(8.0, (float) config('documents.verification_qr.pdf_stamp_mm', 12.0));
    }

    public static function pngPixelSize(): int
    {
        return max(3, (int) config('documents.verification_qr.png_pixel_size', 5));
    }
}
