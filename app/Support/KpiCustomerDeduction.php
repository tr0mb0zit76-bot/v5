<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Вычеты KPI с суммы заказчика по категории оплаты.
 */
final class KpiCustomerDeduction
{
    /**
     * @param  array{
     *     vat_percent: float,
     *     vat_zero_22_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     * }  $rates
     */
    public static function amount(float $customerRate, string $paymentCategory, array $rates): float
    {
        if ($customerRate <= 0) {
            return 0.0;
        }

        return match ($paymentCategory) {
            'cash' => self::percentOf($customerRate, (float) $rates['cash_primary_percent'])
                + self::percentOf($customerRate, (float) $rates['cash_secondary_percent']),
            'vat_zero_22' => self::percentOf($customerRate, (float) $rates['vat_zero_22_percent']),
            'vat', 'cashless' => self::percentOf($customerRate, (float) $rates['vat_percent']),
            default => 0.0,
        };
    }

    public static function effectivePercent(float $customerRate, float $deductionAmount): float
    {
        if ($customerRate <= 0) {
            return 0.0;
        }

        return round(($deductionAmount / $customerRate) * 100, 2);
    }

    private static function percentOf(float $base, float $percent): float
    {
        return $base * ($percent / 100);
    }
}
