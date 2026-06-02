<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Маржа: ставка заказчика за вычетом KPI (эффективный процент уже в $kpiPercent) и минус затраты.
 */
final class CashToCashMarginCalculator
{
    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    public static function isCashToCash(?string $clientPaymentForm, array $contractorsCosts): bool
    {
        if (mb_strtolower(trim((string) $clientPaymentForm), 'UTF-8') !== 'cash') {
            return false;
        }

        if ($contractorsCosts === []) {
            return false;
        }

        foreach ($contractorsCosts as $row) {
            if (! is_array($row)) {
                return false;
            }

            $pf = mb_strtolower(trim((string) ($row['payment_form'] ?? '')), 'UTF-8');
            if ($pf !== 'cash') {
                return false;
            }
        }

        return true;
    }

    public static function margin(float $clientPrice, float $totalCost, float $kpiPercent, bool $cashToCash = false): float
    {
        unset($cashToCash);

        return ($clientPrice * (1 - ($kpiPercent / 100))) - $totalCost;
    }
}
