<?php

declare(strict_types=1);

namespace App\Support;

final class LeadPrecalculationPayloadNormalizer
{
    /**
     * @param  array<string, mixed>|null  $precalculation
     * @return array<string, mixed>
     */
    public static function normalize(?array $precalculation): array
    {
        if (! is_array($precalculation)) {
            return self::blank();
        }

        $goodsLines = [];
        foreach ($precalculation['goods_lines'] ?? [] as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $goodsLines[] = self::normalizeGoodsLine($line, $index);
        }

        $serviceLines = [];
        foreach ($precalculation['service_lines'] ?? [] as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $serviceLines[] = self::normalizeServiceLine($line, $index);
        }

        $status = trim((string) ($precalculation['status'] ?? 'draft'));

        return [
            'status' => in_array($status, ['draft', 'ready', 'archived'], true) ? $status : 'draft',
            'freight' => self::normalizeFreight(is_array($precalculation['freight'] ?? null) ? $precalculation['freight'] : []),
            'goods_lines' => $goodsLines,
            'service_lines' => $serviceLines,
            'computed' => is_array($precalculation['computed'] ?? null) ? $precalculation['computed'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return [
            'status' => 'draft',
            'freight' => self::normalizeFreight([]),
            'goods_lines' => [],
            'service_lines' => [],
            'computed' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $freight
     * @return array{to_border_total: float, after_border_total: float, distribution_basis: string}
     */
    public static function normalizeFreight(array $freight): array
    {
        $basis = trim((string) ($freight['distribution_basis'] ?? 'invoice_rub'));
        if (! in_array($basis, ['invoice_rub', 'weight_kg', 'equal'], true)) {
            $basis = 'invoice_rub';
        }

        return [
            'to_border_total' => max(0.0, (float) ($freight['to_border_total'] ?? 0)),
            'after_border_total' => max(0.0, (float) ($freight['after_border_total'] ?? 0)),
            'distribution_basis' => $basis,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function normalizeGoodsLine(array $line, int $index = 0): array
    {
        $currency = strtoupper(trim((string) ($line['currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        $quantity = max(1, (int) ($line['quantity'] ?? 1));
        $exchangeRate = self::nullableAmount($line['exchange_rate'] ?? null);
        $invoiceAmount = self::nullableAmount($line['invoice_amount'] ?? null);

        return [
            'id' => self::normalizeLineId($line['id'] ?? null, 'goods', $index),
            'description' => self::nullIfEmpty($line['description'] ?? null),
            'tn_ved_code' => ImportCostTnVedCatalog::normalizeCode((string) ($line['tn_ved_code'] ?? '')),
            'tn_ved_label' => self::nullIfEmpty($line['tn_ved_label'] ?? null),
            'quantity' => $quantity,
            'weight_kg' => self::nullableAmount($line['weight_kg'] ?? null),
            'invoice_amount' => $invoiceAmount,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'freight_to_border' => max(0.0, (float) ($line['freight_to_border'] ?? 0)),
            'freight_after_border' => max(0.0, (float) ($line['freight_after_border'] ?? 0)),
            'other_costs' => max(0.0, (float) ($line['other_costs'] ?? 0)),
            'vehicle_age_years' => max(0, (int) ($line['vehicle_age_years'] ?? 0)),
            'include_utilization_fee' => (bool) ($line['include_utilization_fee'] ?? true),
            'duty_percent_override' => self::nullableAmount($line['duty_percent_override'] ?? null),
            'vat_percent_override' => self::nullableAmount($line['vat_percent_override'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function normalizeServiceLine(array $line, int $index = 0): array
    {
        $kind = trim((string) ($line['kind'] ?? 'other'));
        if (! in_array($kind, ['logistics', 'other'], true)) {
            $kind = 'other';
        }

        $currency = strtoupper(trim((string) ($line['currency'] ?? 'RUB')));
        if ($currency === '') {
            $currency = 'RUB';
        }

        $stage = self::nullIfEmpty($line['stage'] ?? null);
        if ($stage !== null) {
            $stage = LeadPerformerPayloadNormalizer::normalizeOne(['stage' => $stage])['stage'];
        }

        return [
            'id' => self::normalizeLineId($line['id'] ?? null, 'service', $index),
            'kind' => $kind,
            'title' => self::nullIfEmpty($line['title'] ?? null) ?? ($kind === 'logistics' ? 'Логистика' : 'Услуга'),
            'stage' => $stage,
            'amount' => self::nullableAmount($line['amount'] ?? null),
            'currency' => $currency,
        ];
    }

    private static function normalizeLineId(mixed $id, string $prefix, int $index): string
    {
        $value = trim((string) ($id ?? ''));

        if ($value !== '') {
            return $value;
        }

        return $prefix.'_'.($index + 1);
    }

    private static function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;

        if (! is_finite($numeric) || $numeric < 0) {
            return null;
        }

        return round($numeric, 2);
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
