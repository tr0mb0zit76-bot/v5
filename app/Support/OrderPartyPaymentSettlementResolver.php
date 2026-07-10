<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\PaymentSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Оплата стороны заказа по корневым строкам графика (fallback, если payment_statuses пуст).
 */
final class OrderPartyPaymentSettlementResolver
{
    public static function isPartyFullyPaid(Order $order, string $party): bool
    {
        if (! in_array($party, ['customer', 'carrier'], true) || ! Schema::hasTable('payment_schedules')) {
            return false;
        }

        $roots = self::rootSchedulesForParty($order, $party);

        if ($roots->isEmpty()) {
            return false;
        }

        return self::dedupeLatestRoots($roots)->every(
            fn (PaymentSchedule $schedule): bool => self::scheduleIsSettled($schedule),
        );
    }

    /**
     * @return Collection<int, PaymentSchedule>
     */
    private static function rootSchedulesForParty(Order $order, string $party): Collection
    {
        $query = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', $party)
            ->where('status', '!=', 'cancelled');

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($partialQuery): void {
                $partialQuery->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        return $query->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, PaymentSchedule>  $roots
     * @return Collection<int, PaymentSchedule>
     */
    private static function dedupeLatestRoots(Collection $roots): Collection
    {
        return $roots
            ->groupBy(fn (PaymentSchedule $schedule): string => implode('|', [
                (string) $schedule->party,
                (string) $schedule->type,
                (string) (Schema::hasColumn('payment_schedules', 'installment_sequence')
                    ? ($schedule->installment_sequence ?? $schedule->type)
                    : $schedule->type),
                (string) ((int) ($schedule->counterparty_id ?? 0)),
            ]))
            ->map(fn (Collection $group): PaymentSchedule => $group->sortByDesc('id')->first())
            ->values();
    }

    private static function scheduleIsSettled(PaymentSchedule $schedule): bool
    {
        if ($schedule->status === 'paid') {
            return true;
        }

        $amount = round((float) $schedule->amount, 2);
        $paidAmount = round((float) ($schedule->paid_amount ?? 0), 2);
        $remainingAmount = PaymentScheduleSettlementStatus::outstandingAmount(
            $amount,
            $paidAmount,
            $schedule->remaining_amount !== null ? (float) $schedule->remaining_amount : null,
        );

        return PaymentScheduleSettlementStatus::isFullySettled($amount, $paidAmount, $remainingAmount);
    }
}
