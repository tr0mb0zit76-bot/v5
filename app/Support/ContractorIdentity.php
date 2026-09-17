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

    /**
     * ИП: legal_form=ip или 12-значный ИНН физлица.
     */
    public static function isIndividualEntrepreneur(mixed $legalForm = null, mixed $inn = null): bool
    {
        $form = strtolower(trim((string) ($legalForm ?? '')));

        if ($form === 'ip') {
            return true;
        }

        $normalizedInn = self::normalizeInn($inn);

        return $normalizedInn !== null && strlen($normalizedInn) === 12;
    }
}
