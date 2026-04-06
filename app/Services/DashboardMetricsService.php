<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function __construct(
        private readonly DealTypeClassifier $dealTypeClassifier,
    ) {}

    /**
     * @return array{
     *     total_orders:int,
     *     direct_orders:int,
     *     direct_share_percent:float,
     *     period_delta:float,
     *     weekly_client_returns:float,
     *     tasks_today:int,
     *     tasks_overdue:int,
     *     plan_completion_percent:float,
     *     margin_rank:string
     * }
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
                'order_customer_date',
                'customer_rate',
                'payment_statuses',
            ]);

        $totalOrders = $orders->count();
        $directOrders = $orders
            ->filter(fn (Order $order): bool => $this->dealTypeClassifier->classify($order) === 'direct')
            ->count();

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $weeklyClientReturns = $orders
            ->filter(fn (Order $order): bool => $this->isPaymentScheduledThisWeek($order, $weekStart, $weekEnd))
            ->sum(fn (Order $order): float => (float) ($order->customer_rate ?? 0));

        return [
            'total_orders' => $totalOrders,
            'direct_orders' => $directOrders,
            'direct_share_percent' => $totalOrders > 0 ? round(($directOrders / $totalOrders) * 100, 2) : 0.0,
            'period_delta' => round($orders->sum(fn (Order $order): float => (float) ($order->delta ?? 0)), 2),
            'weekly_client_returns' => round($weeklyClientReturns, 2),
            'tasks_today' => 0,
            'tasks_overdue' => 0,
            'plan_completion_percent' => 0.0,
            'margin_rank' => '—',
        ];
    }

    private function isPaymentScheduledThisWeek(Order $order, Carbon $weekStart, Carbon $weekEnd): bool
    {
        $paymentDate = $this->customerPaymentDate($order);

        if ($paymentDate === null) {
            return false;
        }

        return $paymentDate->between($weekStart, $weekEnd);
    }

    private function customerPaymentDate(Order $order): ?Carbon
    {
        $payload = (array) ($order->payment_statuses ?? []);
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $candidate = data_get($customer, 'payment_date')
            ?? data_get($customer, 'paid_at')
            ?? data_get($customer, 'paymentDate')
            ?? data_get($customer, 'paymentDateTime');

        if (! $candidate) {
            return null;
        }

        try {
            return Carbon::parse($candidate);
        } catch (\Throwable) {
            return null;
        }
    }
}
