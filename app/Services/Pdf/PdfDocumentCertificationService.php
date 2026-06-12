<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfDocumentCertificationService
{
    public function isEnabled(): bool
    {
        if (! (bool) config('pdf_signing.enabled', false)) {
            return false;
        }

        $certificatePath = (string) config('pdf_signing.certificate_path');
        $privateKeyPath = (string) config('pdf_signing.private_key_path');

        return is_readable($certificatePath) && is_readable($privateKeyPath);
    }

    /**
     * Удостоверяет PDF certifying signature (DocMDP).
     *
     * @return array{certified_pdf: string, sha256: string, docmdp: int}|null
     */
    public function certify(string $pdfContents): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! str_starts_with($pdfContents, '%PDF')) {
            return null;
        }

        $temporaryPdfPath = tempnam(sys_get_temp_dir(), 'crm-pdf-certify-');
        if ($temporaryPdfPath === false) {
            return null;
        }

        try {
            if (file_put_contents($temporaryPdfPath, $pdfContents) === false) {
                return null;
            }

            $pdf = new Fpdi;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pageCount = $pdf->setSourceFile($temporaryPdfPath);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $templateSize = $pdf->getTemplateSize($templateId);
                $orientation = ($templateSize['width'] ?? 0) > ($templateSize['height'] ?? 0) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$templateSize['width'], $templateSize['height']]);
                $pdf->useTemplate($templateId);
            }

            $appearance = config('pdf_signing.appearance');
            $appearance = is_array($appearance) ? $appearance : [];

            $pdf->setSignatureAppearance(
                (float) ($appearance['x_mm'] ?? 130),
                (float) ($appearance['y_mm'] ?? 255),
                (float) ($appearance['w_mm'] ?? 60),
                (float) ($appearance['h_mm'] ?? 18),
                $pageCount,
                (string) config('pdf_signing.signer_name'),
            );

            $docmdp = max(1, min(3, (int) config('pdf_signing.docmdp', 2)));

            $pdf->setSignature(
                $this->certificateUri(),
                $this->privateKeyUri(),
                (string) config('pdf_signing.private_key_password', ''),
                '',
                $docmdp,
                [
                    'Name' => (string) config('pdf_signing.signer_name'),
                    'Location' => (string) config('pdf_signing.signer_location'),
                    'Reason' => (string) config('pdf_signing.signer_reason'),
                ],
            );

            $certifiedPdf = $pdf->Output('', 'S');
            if (! is_string($certifiedPdf) || ! str_starts_with($certifiedPdf, '%PDF')) {
                return null;
            }

            return [
                'certified_pdf' => $certifiedPdf,
                'sha256' => hash('sha256', $certifiedPdf),
                'docmdp' => $docmdp,
            ];
        } catch (\Throwable $exception) {
            Log::warning('pdf.certify_failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($temporaryPdfPath);
        }
    }

    private function certificateUri(): string
    {
        return 'file://'.$this->normalizePath((string) config('pdf_signing.certificate_path'));
    }

    private function privateKeyUri(): string
    {
        return 'file://'.$this->normalizePath((string) config('pdf_signing.private_key_path'));
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
