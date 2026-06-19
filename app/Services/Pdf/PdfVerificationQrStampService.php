<?php

namespace App\Services\Pdf;

use App\Models\OrderDocument;
use App\Support\PrintFormVerificationCode;
use App\Support\PrintFormVerificationQrDimensions;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF2DBarcode;

class PdfVerificationQrStampService
{
    /**
     * @return array{pdf: string, url: string, code: string, stamped_sha256: string}|null
     */
    public function stamp(string $pdfContents, OrderDocument $document): ?array
    {
        if (! str_starts_with($pdfContents, '%PDF')) {
            return null;
        }

        $temporaryPdfPath = tempnam(sys_get_temp_dir(), 'crm-pdf-qr-source-');
        $temporaryQrPath = tempnam(sys_get_temp_dir(), 'crm-pdf-qr-image-');
        if ($temporaryPdfPath === false || $temporaryQrPath === false) {
            return null;
        }

        try {
            if (file_put_contents($temporaryPdfPath, $pdfContents) === false) {
                return null;
            }

            $code = PrintFormVerificationCode::forOrderDocument($document);
            $url = route('print-verification.order-documents.show', [
                'orderDocument' => $document->id,
                'code' => $code,
            ]);

            $pixelSize = PrintFormVerificationQrDimensions::pngPixelSize();
            $qr = new TCPDF2DBarcode($url, 'QRCODE,H');
            $qrPng = $qr->getBarcodePngData($pixelSize, $pixelSize, [0, 0, 0]);
            if (! is_string($qrPng) || $qrPng === '' || file_put_contents($temporaryQrPath, $qrPng) === false) {
                return null;
            }

            $pdf = new Fpdi;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);

            $pageCount = $pdf->setSourceFile($temporaryPdfPath);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $templateSize = $pdf->getTemplateSize($templateId);
                $width = (float) ($templateSize['width'] ?? 210);
                $height = (float) ($templateSize['height'] ?? 297);
                $orientation = $width > $height ? 'L' : 'P';

                $pdf->AddPage($orientation, [$width, $height]);
                $pdf->useTemplate($templateId);
                $this->placeStamp($pdf, $temporaryQrPath, $width, $height, $code);
            }

            $stampedPdf = $pdf->Output('', 'S');
            if (! is_string($stampedPdf) || ! str_starts_with($stampedPdf, '%PDF')) {
                return null;
            }

            return [
                'pdf' => $stampedPdf,
                'url' => $url,
                'code' => $code,
                'stamped_sha256' => hash('sha256', $stampedPdf),
            ];
        } catch (\Throwable $exception) {
            Log::warning('pdf.verification_qr_failed', [
                'order_document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($temporaryPdfPath);
            @unlink($temporaryQrPath);
        }
    }

    private function placeStamp(Fpdi $pdf, string $qrPath, float $pageWidth, float $pageHeight, string $code): void
    {
        $qrSize = PrintFormVerificationQrDimensions::pdfStampSizeMm();
        $margin = 8.0;
        $x = max($margin, $pageWidth - $qrSize - $margin);
        $y = max($margin, $pageHeight - $qrSize - $margin);

        $pdf->Image($qrPath, $x, $y, $qrSize, $qrSize, 'PNG');
        $pdf->SetFont('dejavusans', '', 6);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->SetXY($margin, $pageHeight - 12);
        $pdf->Cell(
            max(10, $pageWidth - $qrSize - ($margin * 3)),
            4,
            'Проверка подлинности по QR. Код: '.$code,
            0,
            0,
            'L'
        );
    }
}
