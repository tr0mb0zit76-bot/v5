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
        $remainingAmount = round(
            (float) ($schedule->remaining_amount ?? max(0, $amount - $paidAmount)),
            2,
        );

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
