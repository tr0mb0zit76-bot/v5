<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Определяет, задаёт ли JSON графика более одного платежа (транши или аванс + остаток).
 */
final class PaymentScheduleStructure
{
    /**
     * @param  array<string, mixed>  $schedule
     */
    public static function definesMultiplePayments(array $schedule): bool
    {
        if (PaymentInstallmentScheduleNormalizer::isInstallmentModel($schedule)) {
            $rows = array_values(array_filter(
                $schedule['installments'] ?? [],
                static fn (mixed $row): bool => is_array($row),
            ));

            return count($rows) >= 2;
        }

        if (! (bool) ($schedule['has_prepayment'] ?? false)) {
            return false;
        }

        $ratio = (float) ($schedule['prepayment_ratio'] ?? 0);

        return $ratio > 0 && $ratio < 100;
    }
}
