<?php

namespace App\Support;

class DocumentPreview
{
    /**
     * Resolve preview driver from environment (used from config only at bootstrap).
     */
    public static function resolvedDriverFromEnv(): string
    {
        $raw = env('DOC_PREVIEW_DRIVER');
        if (is_string($raw) && trim($raw) !== '') {
            return strtolower(trim($raw));
        }

        $url = env('GOTENBERG_URL');
        if (is_string($url) && trim($url) !== '') {
            return 'gotenberg';
        }

        return 'html';
    }

    /**
     * @return array{
     *     driver: string,
     *     gotenberg_url_configured: bool,
     *     pdf_preview_available: bool,
     *     hint: string
     * }
     */
    public static function inertiaMeta(): array
    {
        $driver = strtolower((string) config('document_preview.driver', 'html'));
        $url = trim((string) config('document_preview.gotenberg.url', ''));
        $pdf = $driver === 'gotenberg' && $url !== '';

        $hint = match (true) {
            $pdf => 'PDF-предпросмотр DOCX через Gotenberg включён — можно выравнивать печать и подпись по макету.',
            $driver === 'gotenberg' && $url === '' => 'Режим Gotenberg выбран, но GOTENBERG_URL пустой — укажите URL API (например http://127.0.0.1:3000) и выполните php artisan config:clear.',
            default => 'Предпросмотр DOCX сейчас без PDF-макета. Для выравнивания печати и подписи включите Gotenberg: DOC_PREVIEW_DRIVER=gotenberg и GOTENBERG_URL=… (или задайте только GOTENBERG_URL, если DOC_PREVIEW_DRIVER не указан).',
        };

        return [
            'driver' => $driver,
            'gotenberg_url_configured' => $url !== '',
            'pdf_preview_available' => $pdf,
            'hint' => $hint,
        ];
    }
}
