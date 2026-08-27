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
     * Нормализует блок financial_term для wizard_state (заказчик + перевозчики по плечам).
     *
     * @param  array<string, mixed>  $financialTerm
     * @return array<string, mixed>
     */
    /**
     * @param  list<array<string, mixed>>|null  $performers
     */
    public static function normalizeFinancialTermForStorage(array $financialTerm, ?array $performers = null): array
    {
        $out = $financialTerm;

        $client = self::normalizeForStorage(
            is_array($out['client_norms_penalties'] ?? null) ? $out['client_norms_penalties'] : null,
        );
        if ($client !== null) {
            $out['client_norms_penalties'] = $client;
        } else {
            unset($out['client_norms_penalties']);
        }

        $out['carrier_norms_by_leg'] = self::normalizeCarrierNormsByLegForStorage(
            $out['carrier_norms_by_leg'] ?? null,
            $performers,
        );

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>|null  $performers  optional; fills missing stage by index
     * @return list<array<string, mixed>>
     */
    public static function normalizeCarrierNormsByLegForStorage(mixed $rows, ?array $performers = null): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = self::normalizeForStorage($row);
            if ($normalized === null) {
                continue;
            }

            $stage = trim((string) ($row['stage'] ?? ''));
            if ($stage === '' && is_array($performers)) {
                $stage = trim((string) ($performers[$index]['stage'] ?? ''));
            }
            if ($stage !== '') {
                $normalized['stage'] = $stage;
            }

            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * Штрафы/нормативы не входят в блокировку финансовых ставок — подмешиваем из запроса.
     *
     * @param  array<string, mixed>|null  $incomingFinancial
     * @param  array<string, mixed>  $preservedFinancial
     * @return array<string, mixed>
     */
    public static function mergeIncomingNormsIntoFinancialTerm(?array $incomingFinancial, array $preservedFinancial): array
    {
        if (! is_array($incomingFinancial)) {
            return $preservedFinancial;
        }

        if (array_key_exists('client_norms_penalties', $incomingFinancial)) {
            $preservedFinancial['client_norms_penalties'] = $incomingFinancial['client_norms_penalties'];
        }

        if (array_key_exists('carrier_norms_by_leg', $incomingFinancial)) {
            $preservedFinancial['carrier_norms_by_leg'] = $incomingFinancial['carrier_norms_by_leg'];
        }

        return $preservedFinancial;
    }

    /**
     * Сводная строка для плейсхолдера ${normativ} и каталога переменных.
     *
     * @param  array<string, mixed>|null  $row
     */
    public static function formatSummaryForPrint(?array $row): ?string
    {
        if ($row === null) {
            return null;
        }

        $parts = [];

        $loading = self::nullableFloat($row['norm_loading_hours'] ?? null);
        if ($loading !== null) {
            $parts[] = 'Погрузка: '.self::formatHoursForSummary($loading).' ч';
        }

        $customs = self::nullableFloat($row['norm_customs_hours'] ?? null);
        if ($customs !== null) {
            $parts[] = 'Таможня: '.self::formatHoursForSummary($customs).' ч';
        }

        $unloading = self::nullableFloat($row['norm_unloading_hours'] ?? null);
        if ($unloading !== null) {
            $parts[] = 'Выгрузка: '.self::formatHoursForSummary($unloading).' ч';
        }

        $miss = self::nullableFloat($row['miss_amount'] ?? null);
        if ($miss !== null) {
            $parts[] = 'Срыв: '.self::formatMoneyForSummary($miss).' '.self::currencyCode($row['miss_currency'] ?? null, 'RUB');
        }

        $downtime = self::nullableFloat($row['downtime_amount'] ?? null);
        if ($downtime !== null) {
            $parts[] = 'Простой: '.self::formatMoneyForSummary($downtime).' '.self::currencyCode($row['downtime_currency'] ?? null, 'RUB');
        }

        $fine = self::nullableFloat($row['fine_amount'] ?? null);
        if ($fine !== null) {
            $parts[] = 'Штраф: '.self::formatMoneyForSummary($fine).' '.self::currencyCode($row['fine_currency'] ?? null, 'RUB');
        }

        $penalty = trim((string) ($row['penalty_terms'] ?? ''));
        if ($penalty !== '') {
            $parts[] = 'Пеня: '.$penalty;
        }

        return $parts === [] ? null : implode('; ', $parts);
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
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private static function formatHoursForSummary(float $hours): string
    {
        $formatted = rtrim(rtrim(number_format($hours, 2, ',', ' '), '0'), ',');

        return $formatted !== '' ? $formatted : '0';
    }

    private static function formatMoneyForSummary(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
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
