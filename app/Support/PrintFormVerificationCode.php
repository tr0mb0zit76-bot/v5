<?php

namespace App\Support;

use App\Models\OrderDocument;

final class PrintFormVerificationCode
{
    public static function forOrderDocument(OrderDocument $document): string
    {
        $payload = implode('|', [
            'order_document',
            (string) $document->id,
            (string) ($document->order_id ?? ''),
            (string) ($document->created_at?->timestamp ?? ''),
        ]);

        return strtoupper(substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 16));
    }

    public static function matchesOrderDocument(OrderDocument $document, string $code): bool
    {
        return hash_equals(self::forOrderDocument($document), strtoupper(trim($code)));
    }
}
