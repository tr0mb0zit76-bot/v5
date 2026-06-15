<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Фактически полученные от заказчика суммы по графику оплат (включая частичные платежи).
 */
final class CustomerPaymentAmountResolver
{
    public static function paidForOrderUntil(int $orderId, ?string $untilDate = null): float
    {
        if (Schema::hasTable('payment_schedule_payment_events')
            && self::orderHasLedgerEvents($orderId)) {
            return round(self::sumFromLedger($orderId, $untilDate), 2);
        }

        return round(self::sumFromSchedules($orderId, $untilDate), 2);
    }

    private static function orderHasLedgerEvents(int $orderId): bool
    {
        $query = DB::table('payment_schedule_payment_events')
            ->where('order_id', $orderId)
            ->where('party', 'customer');

        self::applyActiveLedgerScope($query);

        return $query->exists();
    }

    private static function sumFromLedger(int $orderId, ?string $untilDate): float
    {
        $query = DB::table('payment_schedule_payment_events')
            ->where('order_id', $orderId)
            ->where('party', 'customer');

        self::applyActiveLedgerScope($query);

        if ($untilDate !== null && $untilDate !== '') {
            $query->whereDate('payment_date', '<=', substr($untilDate, 0, 10));
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  Builder  $query
     */
    private static function applyActiveLedgerScope($query): void
    {
        if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }
    }

    private static function sumFromSchedules(int $orderId, ?string $untilDate): float
    {
        if (! Schema::hasTable('payment_schedules')) {
            return 0.0;
        }

        if ($untilDate === null || $untilDate === '') {
            return self::sumRootPaidAmounts($orderId);
        }

        $total = 0.0;

        foreach (self::rootScheduleRows($orderId) as $row) {
            $total += self::paidOnRootRowUntil($row, $untilDate);
        }

        return $total;
    }

    private static function sumRootPaidAmounts(int $orderId): float
    {
        if (! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            return self::sumRootPaidAmountsFromStatus($orderId);
        }

        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->where('party', 'customer')
            ->where('status', '!=', 'cancelled');

        self::applyRootRowScope($query);

        return (float) $query->sum(DB::raw('COALESCE(paid_amount, 0)'));
    }

    private static function sumRootPaidAmountsFromStatus(int $orderId): float
    {
        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->where('party', 'customer')
            ->where('status', 'paid');

        self::applyRootRowScope($query);

        return (float) $query->sum('amount');
    }

    /**
     * @return list<object{id: int, amount: mixed, paid_amount?: mixed, actual_date?: mixed, status?: mixed}>
     */
    private static function rootScheduleRows(int $orderId): array
    {
        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->where('party', 'customer')
            ->where('status', '!=', 'cancelled');

        self::applyRootRowScope($query);

        return $query->get()->all();
    }

    /**
     * @param  Builder  $query
     */
    private static function applyRootRowScope($query): void
    {
        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')
                    ->orWhere('is_partial', false);
            });
        }
    }

    /**
     * @param  object{id: int, amount: mixed, paid_amount?: mixed, actual_date?: mixed, status?: mixed}  $row
     */
    private static function paidOnRootRowUntil(object $row, string $untilDate): float
    {
        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $fromPartials = self::sumPartialPaymentsUntil((int) $row->id, $untilDate);
            if ($fromPartials > 0) {
                return min($fromPartials, (float) $row->amount);
            }
        }

        $rootPaid = self::rootPaidAmount($row);
        if ($rootPaid <= 0) {
            return 0.0;
        }

        $latestDate = self::latestPaymentDateForRow($row);
        if ($latestDate === null) {
            return min($rootPaid, (float) $row->amount);
        }

        if (! self::paymentDateOnOrBefore($latestDate, $untilDate)) {
            return 0.0;
        }

        return min($rootPaid, (float) $row->amount);
    }

    /**
     * @param  object{paid_amount?: mixed, status?: mixed, amount: mixed}  $row
     */
    private static function rootPaidAmount(object $row): float
    {
        if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
            $paid = (float) ($row->paid_amount ?? 0);
            if ($paid > 0) {
                return $paid;
            }
        }

        if (($row->status ?? '') === 'paid') {
            return (float) $row->amount;
        }

        return 0.0;
    }

    private static function sumPartialPaymentsUntil(int $rootId, string $untilDate): float
    {
        $partialQuery = DB::table('payment_schedules')
            ->where('parent_payment_id', $rootId);

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $partialQuery->where('is_partial', true);
        }

        $sum = 0.0;

        foreach ($partialQuery->get(['amount', 'paid_amount', 'actual_date']) as $partial) {
            if (! self::paymentDateOnOrBefore($partial->actual_date ?? null, $untilDate)) {
                continue;
            }

            $sum += Schema::hasColumn('payment_schedules', 'paid_amount')
                ? (float) ($partial->paid_amount ?? $partial->amount ?? 0)
                : (float) ($partial->amount ?? 0);
        }

        return $sum;
    }

    /**
     * @param  object{id: int, actual_date?: mixed}  $row
     */
    private static function latestPaymentDateForRow(object $row): ?string
    {
        $dates = [];

        if (! empty($row->actual_date)) {
            $dates[] = substr((string) $row->actual_date, 0, 10);
        }

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $partialQuery = DB::table('payment_schedules')
                ->where('parent_payment_id', $row->id);

            if (Schema::hasColumn('payment_schedules', 'is_partial')) {
                $partialQuery->where('is_partial', true);
            }

            foreach ($partialQuery->pluck('actual_date') as $actualDate) {
                if ($actualDate !== null && $actualDate !== '') {
                    $dates[] = substr((string) $actualDate, 0, 10);
                }
            }
        }

        if ($dates === []) {
            return null;
        }

        return max($dates);
    }

    private static function paymentDateOnOrBefore(mixed $actualDate, string $untilDate): bool
    {
        if ($actualDate === null || $actualDate === '') {
            return false;
        }

        return substr((string) $actualDate, 0, 10) <= substr($untilDate, 0, 10);
    }
}
