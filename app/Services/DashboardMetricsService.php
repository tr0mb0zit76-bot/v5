<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function __construct(
        private readonly DealTypeClassifier $dealTypeClassifier,
    ) {}

    /**
     * @return array{total_orders:int,direct_orders:int,direct_share_percent:float,period_delta:float}
     */
    public function forManager(int $managerId, string $dateFrom, string $dateTo): array
    {
        $orders = Order::query()
            ->where('manager_id', $managerId)
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->get([
                'id',
                'customer_payment_form',
                'carrier_payment_form',
                'delta',
            ]);

        $totalOrders = $orders->count();
        $directOrders = $orders
            ->filter(fn (Order $order): bool => $this->dealTypeClassifier->classify($order) === 'direct')
            ->count();

        return [
            'total_orders' => $totalOrders,
            'direct_orders' => $directOrders,
            'direct_share_percent' => $totalOrders > 0 ? round(($directOrders / $totalOrders) * 100, 2) : 0.0,
            'period_delta' => round($orders->sum(fn (Order $order): float => (float) ($order->delta ?? 0)), 2),
        ];
    }
}
