<?php

namespace App\Services;

use App\Support\ApplicationTempDirectory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocxPdfPreviewService
{
    public function convertToPdf(string $docxContents, string $fileName = 'document.docx'): ?string
    {
        return $this->convertOfficeDocumentToPdf($docxContents, $fileName);
    }

    public function convertOfficeDocumentToPdf(string $contents, string $fileName): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $url = rtrim((string) config('document_preview.gotenberg.url', ''), '/');
        if ($url === '') {
            Log::warning('Office->PDF preview skipped: empty GOTENBERG_URL');

            return null;
        }

        $tmpPath = ApplicationTempDirectory::tempFile('office-preview-');

        file_put_contents($tmpPath, $contents);

        try {
            $response = Http::timeout((int) config('document_preview.gotenberg.timeout', 60))
                ->attach('files', file_get_contents($tmpPath), $this->normalizeOfficeFileName($fileName))
                ->post($url.'/forms/libreoffice/convert');

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            return (string) $response->body();
        } catch (\Throwable $e) {
            $context = ['message' => $e->getMessage(), 'file' => $fileName];
            if ($e instanceof RequestException && $e->response !== null) {
                $context['status'] = $e->response->status();
                $context['body_preview'] = Str::limit((string) $e->response->body(), 500);
            }
            Log::warning('Office->PDF preview conversion failed', $context);

            return null;
        } finally {
            @unlink($tmpPath);
        }
    }

    public function isEnabled(): bool
    {
        return strtolower((string) config('document_preview.driver', 'html')) === 'gotenberg';
    }

    private function normalizeFileName(string $fileName): string
    {
        return $this->normalizeOfficeFileName($fileName);
    }

    private function normalizeOfficeFileName(string $fileName): string
    {
        $trimmed = trim($fileName);

        if ($trimmed === '') {
            return 'document.docx';
        }

        $extension = strtolower((string) pathinfo($trimmed, PATHINFO_EXTENSION));

        if (in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'rtf', 'ppt', 'pptx'], true)) {
            return $trimmed;
        }

        return str_ends_with(strtolower($trimmed), '.docx') ? $trimmed : $trimmed.'.docx';
    }
}
