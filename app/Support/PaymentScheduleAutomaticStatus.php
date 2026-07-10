<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
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
        $today = Carbon::today()->startOfDay();

        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->whereNull('parent_payment_id')
            ->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            })
            ->whereNotIn('status', ['paid', 'cancelled']);

        $columns = ['id', 'planned_date', 'amount'];
        if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
            $columns[] = 'paid_amount';
        }
        if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            $columns[] = 'remaining_amount';
        }

        foreach ($query->get($columns) as $row) {
            $amount = (float) ($row->amount ?? 0);
            $paidAmount = (float) ($row->paid_amount ?? 0);
            $remainingAmount = PaymentScheduleSettlementStatus::outstandingAmount(
                $amount,
                $paidAmount,
                Schema::hasColumn('payment_schedules', 'remaining_amount') && $row->remaining_amount !== null
                    ? (float) $row->remaining_amount
                    : null,
            );

            if (PaymentScheduleSettlementStatus::isFullySettled($amount, $paidAmount, $remainingAmount)) {
                $update = [
                    'status' => 'paid',
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
                    $update['remaining_amount'] = 0;
                }

                DB::table('payment_schedules')->where('id', $row->id)->update($update);

                continue;
            }

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

    public static function refreshForUser(?User $user): void
    {
        $query = DB::table('orders')
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at'),
            );

        if ($user !== null) {
            $area = RoleAccess::resolvePaymentScheduleVisibilityAreaForUser($user);
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $user, $area);
        }

        $orderIds = $query
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($orderIds as $orderId) {
            self::refreshForOrder($orderId);
        }
    }

    /**
     * @param  'all'|'own'|'department'  $ordersScope
     *
     * @deprecated Use {@see refreshForUser()} — учитывает department и order_owner/dispatcher.
     */
    public static function refreshForOrdersScope(?int $userId, ?string $roleName, string $ordersScope): void
    {
        if ($userId === null || $roleName === 'admin' || $ordersScope === 'all') {
            self::refreshForUser(null);

            return;
        }

        self::refreshForUser(User::query()->find($userId));
    }
}
