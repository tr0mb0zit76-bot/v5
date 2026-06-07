<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Локальный OCR sidecar (deploy/ocr, см. docs/order-intake-ocr-service.md)
    |--------------------------------------------------------------------------
    |
    | enabled: вызывать OCR_SERVICE_URL, если DocumentTextExtractor не нашёл текст.
    | driver: local — HTTP sidecar; none — только текстовый слой / DOCX.
    |
    */

    'enabled' => filter_var(env('ORDER_INTAKE_OCR', 'none'), FILTER_VALIDATE_BOOL)
        || strtolower((string) env('ORDER_INTAKE_OCR', 'none')) === 'local',

    'driver' => strtolower((string) env('ORDER_INTAKE_OCR', 'none')) === 'local' ? 'local' : 'none',

    'url' => rtrim((string) env('OCR_SERVICE_URL', ''), '/'),

    'timeout' => max(10, (int) env('OCR_SERVICE_TIMEOUT', 120)),

    /*
    |--------------------------------------------------------------------------
    | Оптимизация PDF перед загрузкой документов (POST /optimize на sidecar)
    |--------------------------------------------------------------------------
    |
    | Требует OCR_SERVICE_URL. Не зависит от ORDER_INTAKE_OCR.
    |
    */
    'optimize_enabled' => filter_var(env('DOCUMENT_OPTIMIZE_ENABLED', true), FILTER_VALIDATE_BOOL),
];
