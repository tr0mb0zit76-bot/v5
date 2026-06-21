<?php

namespace App\Services\Commercial;

use App\Support\ApplicationTempDirectory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadProposalPdfService
{
    public function convertHtmlToPdf(string $html, string $fileName = 'proposal.pdf'): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $url = rtrim((string) config('document_preview.gotenberg.url', ''), '/');
        if ($url === '') {
            Log::warning('HTML->PDF skipped: empty GOTENBERG_URL');

            return null;
        }

        $tmpPath = ApplicationTempDirectory::tempFile('html-proposal-');
        file_put_contents($tmpPath, $html);

        try {
            $response = Http::timeout((int) config('document_preview.gotenberg.timeout', 60))
                ->attach('files', file_get_contents($tmpPath), 'index.html')
                ->post($url.'/forms/chromium/convert/html');

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
            Log::warning('HTML->PDF conversion failed', $context);

            return null;
        } finally {
            @unlink($tmpPath);
        }
    }

    public function isEnabled(): bool
    {
        return strtolower((string) config('document_preview.driver', 'html')) === 'gotenberg';
    }
}
