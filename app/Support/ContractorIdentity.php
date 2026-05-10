<?php

namespace App\Support;

final class ContractorIdentity
{
    public static function normalizeName(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public static function normalizeInn(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/u', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
