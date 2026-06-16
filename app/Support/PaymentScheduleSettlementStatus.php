<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PaymentSchedule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Согласование status / paid_amount / remaining_amount для строк графика оплат.
 */
final class PaymentScheduleSettlementStatus
{
    /**
     * Остаток к оплате по строке графика.
     * remaining_amount = 0 при paid_amount = 0 — «ещё не инициализировано», берём полную сумму строки.
     */
    public static function outstandingAmount(float $amount, float $paidAmount = 0, ?float $remainingAmount = null): float
    {
        $amount = round($amount, 2);
        $paidAmount = round($paidAmount, 2);

        if ($remainingAmount !== null) {
            $remaining = round((float) $remainingAmount, 2);

            if ($remaining > 0.009) {
                return $remaining;
            }

            if ($paidAmount <= 0.009) {
                return $amount;
            }
        }

        if ($paidAmount > 0.009) {
            return max(0, round($amount - $paidAmount, 2));
        }

        return $amount;
    }

    public static function isFullySettled(float $amount, float $paidAmount, float $remainingAmount): bool
    {
        if ($amount <= 0 || $paidAmount <= 0.009) {
            return false;
        }

        return $remainingAmount <= 0.01 || $paidAmount >= $amount - 0.01;
    }

    public static function applyToSchedule(PaymentSchedule $schedule): void
    {
        if (! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            return;
        }

        $amount = round((float) $schedule->amount, 2);
        $paidAmount = round((float) ($schedule->paid_amount ?? 0), 2);
        $remainingAmount = round(self::outstandingAmount(
            $amount,
            $paidAmount,
            $schedule->remaining_amount !== null ? (float) $schedule->remaining_amount : null,
        ), 2);

        if (! self::isFullySettled($amount, $paidAmount, $remainingAmount)) {
            return;
        }

        $schedule->status = 'paid';
        $schedule->remaining_amount = 0;

        if ($paidAmount < $amount) {
            $schedule->paid_amount = $amount;
        }
    }

    /**
     * Исключить из «открытых» строки, по которым деньги уже закрыли остаток, но status ещё pending/overdue.
     */
    public static function applyUnsettledRootScope(Builder $query): void
    {
        $query->whereNotIn('payment_schedules.status', ['paid', 'cancelled']);

        if (! Schema::hasColumn('payment_schedules', 'paid_amount')
            || ! Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            return;
        }

        $query->whereNot(function (Builder $settledQuery): void {
            $settledQuery
                ->whereRaw('COALESCE(payment_schedules.paid_amount, 0) > 0.009')
                ->whereRaw('COALESCE(payment_schedules.remaining_amount, 0) <= 0.01');
        });
    }

    public static function outstandingAmountSql(): string
    {
        if (! Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            return 'payment_schedules.amount';
        }

        $unpaidRemainingFallback = Schema::hasColumn('payment_schedules', 'paid_amount')
            ? 'COALESCE(payment_schedules.paid_amount, 0) <= 0.009'
            : '1 = 1';

        return "CASE
            WHEN COALESCE(payment_schedules.paid_amount, 0) > 0.009
                AND COALESCE(payment_schedules.remaining_amount, 0) <= 0.01
            THEN 0
            WHEN payment_schedules.remaining_amount IS NULL
                OR (payment_schedules.remaining_amount <= 0
                    AND payment_schedules.status IN ('pending', 'overdue')
                    AND {$unpaidRemainingFallback})
            THEN payment_schedules.amount
            ELSE payment_schedules.remaining_amount
        END";
    }
}
