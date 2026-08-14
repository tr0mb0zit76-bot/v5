<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Отпечаток банковской операции для дедупа xls↔OData.
 * Не использует «клиент+сумма»: разные счета/заказы с одной ставкой остаются разными.
 */
final class ManagementStatementLineContentFingerprint
{
    public static function key(string $operationDate, string $direction, float $amount, string $description): string
    {
        return hash('sha256', implode('|', [
            $operationDate,
            $direction,
            number_format($amount, 2, '.', ''),
            self::normalizeDescription($description),
        ]));
    }

    public static function normalizeDescription(string $description): string
    {
        $value = mb_strtolower(trim($description));
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/в\s*т\.?\s*ч\.?\s*ндс.*/u', '', $value) ?? $value;
        $value = preg_replace('/сумма\s+[\d\s\-.,]+/u', '', $value) ?? $value;
        $value = preg_replace('/руб(?:лей|ля)?\.?/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function hasDistinguishingToken(string $normalizedDescription): bool
    {
        return preg_match('/\d/u', $normalizedDescription) === 1;
    }
}
