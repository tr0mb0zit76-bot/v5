<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Авто-статусы строк графика оплат: pending (по плану) / overdue относительно «сегодня» и planned_date.
 * Не трогает paid, cancelled, частичные дочерние строки.
 */
final class PaymentScheduleAutomaticStatus
{
    public static function refreshForOrder(int $orderId): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        $today = Carbon::today()->startOfDay();

        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['paid', 'cancelled']);

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        foreach ($query->get(['id', 'planned_date']) as $row) {
            $planned = $row->planned_date ?? null;
            $isOverdue = $planned !== null
                && Carbon::parse((string) $planned)->startOfDay()->lt($today);

            $newStatus = $isOverdue ? 'overdue' : 'pending';

            DB::table('payment_schedules')->where('id', $row->id)->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  'all'|'own'  $ordersScope
     */
    public static function refreshForOrdersScope(?int $userId, ?string $roleName, string $ordersScope): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('orders')) {
            return;
        }

        $orderIds = DB::table('orders')
            ->when(
                $userId !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                fn ($query) => $query->where('manager_id', $userId),
            )
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at'),
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($orderIds as $orderId) {
            self::refreshForOrder($orderId);
        }
    }
}
