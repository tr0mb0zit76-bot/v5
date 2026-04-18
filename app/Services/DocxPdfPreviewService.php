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
        if (! $this->isEnabled()) {
            return null;
        }

        $url = rtrim((string) config('document_preview.gotenberg.url', ''), '/');
        if ($url === '') {
            Log::warning('DOCX->PDF preview skipped: empty GOTENBERG_URL');

            return null;
        }

        $tmpPath = ApplicationTempDirectory::tempFile('docx-preview-');

        file_put_contents($tmpPath, $docxContents);

        try {
            $response = Http::timeout((int) config('document_preview.gotenberg.timeout', 60))
                ->attach('files', file_get_contents($tmpPath), $this->normalizeFileName($fileName))
                ->post($url.'/forms/libreoffice/convert');

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            return (string) $response->body();
        } catch (\Throwable $e) {
            $context = ['message' => $e->getMessage()];
            if ($e instanceof RequestException && $e->response !== null) {
                $context['status'] = $e->response->status();
                $context['body_preview'] = Str::limit((string) $e->response->body(), 500);
            }
            Log::warning('DOCX->PDF preview conversion failed', $context);

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
        $trimmed = trim($fileName);

        if ($trimmed === '') {
            return 'document.docx';
        }

        return str_ends_with(strtolower($trimmed), '.docx') ? $trimmed : $trimmed.'.docx';
    }
}
