<?php

namespace App\Console\Commands;

use App\Services\Documents\OcrServiceClient;
use Illuminate\Console\Command;

class ProbeOcrStorageCommand extends Command
{
    protected $signature = 'documents:probe-ocr';

    protected $description = 'Проверяет доступность локального OCR sidecar (deploy/ocr)';

    public function handle(OcrServiceClient $ocrServiceClient): int
    {
        $enabled = $ocrServiceClient->isEnabled();
        $url = (string) config('document_ocr.url', '');

        $this->line('ORDER_INTAKE_OCR / document_ocr.enabled: '.($enabled ? 'yes' : 'no'));
        $this->line('OCR_SERVICE_URL: '.($url !== '' ? $url : '(не задан)'));

        if (! $enabled) {
            $this->warn('OCR выключен. Задайте ORDER_INTAKE_OCR=local и OCR_SERVICE_URL=http://127.0.0.1:3001');

            return self::SUCCESS;
        }

        if ($ocrServiceClient->probeHealth()) {
            $this->info('OK — OCR sidecar отвечает на /health');

            return self::SUCCESS;
        }

        $this->error('OCR sidecar недоступен. Поднимите deploy/ocr (см. docs/order-intake-ocr-service.md).');

        return self::FAILURE;
    }
}
