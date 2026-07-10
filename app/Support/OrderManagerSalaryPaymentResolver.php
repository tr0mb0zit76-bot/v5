<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\SalaryAccrual;
use Illuminate\Support\Facades\Schema;

/**
 * Выплата KPI менеджеру по заказу из модуля «Зарплата» (salary_accruals).
 */
final class OrderManagerSalaryPaymentResolver
{
    public static function isManagerSalaryPaid(Order $order): bool
    {
        if (! Schema::hasTable('salary_accruals')) {
            return false;
        }

        return SalaryAccrual::query()
            ->where('order_id', $order->id)
            ->where('salary_amount', '>', 0)
            ->where('paid_amount_fact', '>', 0)
            ->where('unpaid_amount', '<=', 0.01)
            ->exists();
    }

    public static function paidAmountForOrder(int $orderId): float
    {
        if (! Schema::hasTable('salary_accruals')) {
            return 0.0;
        }

        return round((float) SalaryAccrual::query()
            ->where('order_id', $orderId)
            ->sum('paid_amount_fact'), 2);
    }
}
