<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Определяет, задаёт ли JSON графика более одного платежа (несколько траншей).
 */
final class PaymentScheduleStructure
{
    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    public static function pickRichestSchedule(array $candidates): array
    {
        $best = [];
        $bestScore = -1;

        foreach ($candidates as $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            $score = self::richnessScore($schedule);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $schedule;
            }
        }

        return $best;
    }

    /**
     * Чем выше балл, тем больше платежей должно попасть в payment_schedules.
     *
     * @param  array<string, mixed>  $schedule
     */
    public static function richnessScore(array $schedule): int
    {
        if ($schedule === []) {
            return 0;
        }

        if (PaymentInstallmentScheduleNormalizer::isInstallmentModel($schedule)) {
            $count = count(array_values(array_filter(
                $schedule['installments'] ?? [],
                static fn (mixed $row): bool => is_array($row),
            )));

            if ($count >= 2) {
                return 200 + $count;
            }

            if ($count === 1) {
                return 15;
            }

            return 0;
        }

        if (self::definesMultiplePayments($schedule)) {
            return 100;
        }

        if (filled($schedule['postpayment_days'] ?? null)
            || filled($schedule['prepayment_days'] ?? null)
            || filled($schedule['postpayment_mode'] ?? null)
            || filled($schedule['prepayment_mode'] ?? null)) {
            return 10;
        }

        return 1;
    }

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
