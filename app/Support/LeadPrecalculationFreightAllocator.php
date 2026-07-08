<?php

declare(strict_types=1);

namespace App\Support;

final class LeadPrecalculationFreightAllocator
{
    /**
     * @param  list<array<string, mixed>>  $goodsLines
     * @param  array<string, mixed>  $freight
     * @return array{
     *     goods_lines: list<array<string, mixed>>,
     *     allocation: list<array{line_id: string, freight_to_border: int, freight_after_border: int}>
     * }
     */
    public static function apply(array $goodsLines, array $freight): array
    {
        $normalizedFreight = LeadPrecalculationPayloadNormalizer::normalizeFreight($freight);
        $toBorderTotal = (int) round($normalizedFreight['to_border_total'], 0);
        $afterBorderTotal = (int) round($normalizedFreight['after_border_total'], 0);

        if ($goodsLines === []) {
            return [
                'goods_lines' => [],
                'allocation' => [],
            ];
        }

        $weights = self::weightsForLines($goodsLines, $normalizedFreight['distribution_basis']);
        $toBorderParts = self::splitAmount($toBorderTotal, $weights);
        $afterBorderParts = self::splitAmount($afterBorderTotal, $weights);

        $allocatedLines = [];
        $allocation = [];

        foreach ($goodsLines as $index => $line) {
            $toBorder = ($toBorderParts[$index] ?? 0) + (int) round((float) ($line['freight_to_border'] ?? 0), 0);
            $afterBorder = ($afterBorderParts[$index] ?? 0) + (int) round((float) ($line['freight_after_border'] ?? 0), 0);

            $allocatedLines[] = [
                ...$line,
                'freight_to_border' => max(0, $toBorder),
                'freight_after_border' => max(0, $afterBorder),
            ];

            $allocation[] = [
                'line_id' => (string) ($line['id'] ?? 'goods_'.($index + 1)),
                'freight_to_border' => max(0, $toBorderParts[$index] ?? 0),
                'freight_after_border' => max(0, $afterBorderParts[$index] ?? 0),
            ];
        }

        return [
            'goods_lines' => $allocatedLines,
            'allocation' => $allocation,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $goodsLines
     * @return list<float>
     */
    private static function weightsForLines(array $goodsLines, string $basis): array
    {
        $weights = [];

        foreach ($goodsLines as $line) {
            $weights[] = match ($basis) {
                'weight_kg' => max(0.0, (float) ($line['weight_kg'] ?? 0)),
                'equal' => 1.0,
                default => self::invoiceRubWeight($line),
            };
        }

        if (array_sum($weights) <= 0) {
            return array_fill(0, count($goodsLines), 1.0);
        }

        return $weights;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function invoiceRubWeight(array $line): float
    {
        $invoiceAmount = $line['invoice_amount'] ?? null;
        if ($invoiceAmount === null) {
            return 0.0;
        }

        if (strtoupper((string) ($line['currency'] ?? 'RUB')) === 'RUB') {
            return max(0.0, (float) $invoiceAmount);
        }

        $exchangeRate = $line['exchange_rate'] ?? null;
        if ($exchangeRate === null) {
            return 0.0;
        }

        return max(0.0, round((float) $invoiceAmount * (float) $exchangeRate, 0));
    }

    /**
     * @param  list<float>  $weights
     * @return list<int>
     */
    private static function splitAmount(int $total, array $weights): array
    {
        if ($total <= 0 || $weights === []) {
            return array_fill(0, count($weights), 0);
        }

        $weightSum = array_sum($weights);
        if ($weightSum <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $allocated = [];
        $assigned = 0;
        $lastIndex = count($weights) - 1;

        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex) {
                $allocated[$index] = max(0, $total - $assigned);

                continue;
            }

            $part = (int) round($total * ($weight / $weightSum), 0);
            $allocated[$index] = $part;
            $assigned += $part;
        }

        return $allocated;
    }
}
