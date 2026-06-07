<?php

namespace App\Services\Documents;

use App\Support\ApplicationTempDirectory;
use App\Support\DocumentUploadBudget;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP-клиент локального OCR sidecar (deploy/ocr).
 *
 * @see docs/order-intake-ocr-service.md
 */
final class OcrServiceClient
{
    /**
     * @return array{text: string, method: string, warnings: list<string>}|null null — OCR выключен или сервис недоступен
     */
    public function extractFromPath(string $path, string $extension): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! is_readable($path)) {
            return [
                'text' => '',
                'method' => 'none',
                'warnings' => ['Файл недоступен для OCR.'],
            ];
        }

        $url = (string) config('document_ocr.url', '');
        if ($url === '') {
            Log::warning('OCR skipped: empty OCR_SERVICE_URL');

            return null;
        }

        $fileName = 'upload.'.ltrim(strtolower($extension), '.');

        try {
            $response = Http::timeout((int) config('document_ocr.timeout', 120))
                ->attach('file', file_get_contents($path) ?: '', $fileName)
                ->post($url.'/extract');

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            /** @var array{text?: mixed, method?: mixed, warnings?: mixed} $payload */
            $payload = $response->json();

            $warnings = is_array($payload['warnings'] ?? null)
                ? array_values(array_map(static fn (mixed $row): string => (string) $row, $payload['warnings']))
                : [];

            return [
                'text' => trim((string) ($payload['text'] ?? '')),
                'method' => (string) ($payload['method'] ?? 'ocr'),
                'warnings' => $warnings,
            ];
        } catch (\Throwable $throwable) {
            $context = ['message' => $throwable->getMessage()];
            if ($throwable instanceof RequestException && $throwable->response !== null) {
                $context['status'] = $throwable->response->status();
                $context['body_preview'] = Str::limit((string) $throwable->response->body(), 500);
            }
            Log::warning('OCR service extract failed', $context);

            return [
                'text' => '',
                'method' => 'ocr_failed',
                'warnings' => ['Локальный OCR недоступен: '.$throwable->getMessage()],
            ];
        }
    }

    /**
     * @return array{text: string, method: string, warnings: list<string>}|null
     */
    public function extractFromContents(string $contents, string $fileName): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $tmpPath = ApplicationTempDirectory::tempFile('ocr-upload-');
        file_put_contents($tmpPath, $contents);

        try {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            return $this->extractFromPath($tmpPath, $extension !== '' ? $extension : 'bin');
        } finally {
            @unlink($tmpPath);
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('document_ocr.enabled', false)
            && trim((string) config('document_ocr.url', '')) !== '';
    }

    public function probeHealth(): bool
    {
        if (! $this->isEnabled() && ! $this->isOptimizeEnabled()) {
            return false;
        }

        $url = (string) config('document_ocr.url', '');

        try {
            $response = Http::timeout(5)->get($url.'/health');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isOptimizeEnabled(): bool
    {
        return (bool) config('document_ocr.optimize_enabled', true)
            && trim((string) config('document_ocr.url', '')) !== '';
    }

    /**
     * @return array{
     *     pdf_base64: string,
     *     original_bytes: int,
     *     optimized_bytes: int,
     *     method: string,
     *     warnings: list<string>,
     *     max_bytes: int,
     *     within_budget: bool,
     * }|null
     */
    public function optimizePdfUpload(UploadedFile $file): ?array
    {
        if (! $this->isOptimizeEnabled()) {
            return null;
        }

        $url = (string) config('document_ocr.url', '');
        if ($url === '') {
            return null;
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('document_ocr.timeout', 120))
                ->attach('file', file_get_contents($path) ?: '', $file->getClientOriginalName() ?: 'upload.pdf')
                ->post($url.'/optimize');

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->json();

            $pdfBase64 = (string) ($payload['pdf_base64'] ?? '');
            if ($pdfBase64 === '') {
                return null;
            }

            $optimizedBytes = (int) ($payload['optimized_bytes'] ?? 0);
            $tmpPath = ApplicationTempDirectory::tempFile('pdf-opt-');
            file_put_contents($tmpPath, base64_decode($pdfBase64, true) ?: '');

            try {
                $maxBytes = DocumentUploadBudget::maxBytesForPath($tmpPath, $file->getClientOriginalName());
            } finally {
                @unlink($tmpPath);
            }

            $warnings = is_array($payload['warnings'] ?? null)
                ? array_values(array_map(static fn (mixed $row): string => (string) $row, $payload['warnings']))
                : [];

            return [
                'pdf_base64' => $pdfBase64,
                'original_bytes' => (int) ($payload['original_bytes'] ?? $file->getSize()),
                'optimized_bytes' => $optimizedBytes,
                'method' => (string) ($payload['method'] ?? 'optimize'),
                'warnings' => $warnings,
                'max_bytes' => $maxBytes,
                'within_budget' => $optimizedBytes > 0 && $optimizedBytes <= $maxBytes,
            ];
        } catch (\Throwable $throwable) {
            Log::warning('OCR service optimize failed', [
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
