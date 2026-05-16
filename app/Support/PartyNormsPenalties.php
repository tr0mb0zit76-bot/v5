<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class PartyNormsPenalties
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.miss_amount" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}.miss_currency" => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            "{$prefix}.downtime_amount" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}.downtime_currency" => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            "{$prefix}.fine_amount" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}.fine_currency" => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            "{$prefix}.penalty_terms" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.norm_loading_hours" => ['nullable', 'numeric', 'min:0', 'max:1000'],
            "{$prefix}.norm_customs_hours" => ['nullable', 'numeric', 'min:0', 'max:1000'],
            "{$prefix}.norm_unloading_hours" => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>|null
     */
    public static function normalizeForStorage(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $normalized = [
            'miss_amount' => self::nullableFloat($raw['miss_amount'] ?? null),
            'miss_currency' => self::currencyCode($raw['miss_currency'] ?? null, 'RUB'),
            'downtime_amount' => self::nullableFloat($raw['downtime_amount'] ?? null),
            'downtime_currency' => self::currencyCode($raw['downtime_currency'] ?? null, 'RUB'),
            'fine_amount' => self::nullableFloat($raw['fine_amount'] ?? null),
            'fine_currency' => self::currencyCode($raw['fine_currency'] ?? null, 'RUB'),
            'penalty_terms' => trim((string) ($raw['penalty_terms'] ?? '')),
            'norm_loading_hours' => self::nullableFloat($raw['norm_loading_hours'] ?? null),
            'norm_customs_hours' => self::nullableFloat($raw['norm_customs_hours'] ?? null),
            'norm_unloading_hours' => self::nullableFloat($raw['norm_unloading_hours'] ?? null),
        ];

        return self::hasContent($normalized) ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    public static function hasContent(?array $row): bool
    {
        if ($row === null) {
            return false;
        }

        if (trim((string) ($row['penalty_terms'] ?? '')) !== '') {
            return true;
        }

        foreach (['miss_amount', 'downtime_amount', 'fine_amount', 'norm_loading_hours', 'norm_customs_hours', 'norm_unloading_hours'] as $key) {
            if ($row[$key] !== null) {
                return true;
            }
        }

        return false;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function currencyCode(mixed $value, string $fallback): string
    {
        $code = strtoupper(trim((string) ($value ?? '')));

        if ($code !== '' && in_array($code, CurrencyDictionary::allowedCodes(), true)) {
            return $code;
        }

        return $fallback;
    }
}
