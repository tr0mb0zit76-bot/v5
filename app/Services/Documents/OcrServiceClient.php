<?php

namespace App\Services\Documents;

use App\Support\ApplicationTempDirectory;
use Illuminate\Http\Client\RequestException;
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
        if (! $this->isEnabled()) {
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
}
