<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Вычеты с суммы заказчика по категории оплаты.
 * Для налички: первый процент от суммы заказчика, второй — от остатка после первого.
 */
final class KpiCustomerDeduction
{
    /**
     * @param  array{
     *     vat_percent: float,
     *     vat_all_percent: float,
     *     vat_zero_22_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     *     vat_zero_cash_primary_percent: float,
     *     vat_zero_cash_secondary_percent: float,
     * }  $rates
     */
    public static function amount(float $customerRate, string $paymentCategory, array $rates): float
    {
        if ($customerRate <= 0) {
            return 0.0;
        }

        return match ($paymentCategory) {
            'cash' => self::sequentialCashDeduction(
                $customerRate,
                (float) $rates['cash_primary_percent'],
                (float) $rates['cash_secondary_percent'],
            ),
            'vat_zero_cash' => self::sequentialCashDeduction(
                $customerRate,
                (float) $rates['vat_zero_cash_primary_percent'],
                (float) $rates['vat_zero_cash_secondary_percent'],
            ),
            'vat_zero_22' => self::percentOf($customerRate, (float) $rates['vat_zero_22_percent']),
            'vat_all' => self::percentOf($customerRate, (float) $rates['vat_all_percent']),
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

    private static function sequentialCashDeduction(float $customerRate, float $primaryPercent, float $secondaryPercent): float
    {
        $primaryAmount = self::percentOf($customerRate, $primaryPercent);
        $remainder = $customerRate - $primaryAmount;

        return $primaryAmount + self::percentOf($remainder, $secondaryPercent);
    }
}
