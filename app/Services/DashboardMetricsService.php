<?php

namespace App\Services;

use App\Models\Order;
use App\Support\CarrierPaymentFormResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        $query = Order::query()
            ->where('manager_id', $managerId)
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            );

        if (! Schema::hasColumn('orders', 'carrier_payment_form')) {
            $eager = [];
            if (Schema::hasTable('leg_costs')) {
                $eager[] = 'legs.cost';
            }
            if (Schema::hasTable('financial_terms')) {
                $eager[] = 'financialTerms';
            }
            if ($eager !== []) {
                $query->with($eager);
            }
        }

        $orders = $query->get($this->orderSelectColumnsForMetrics());

        // Частичный select ломает eager-load ног/стоимостей для подстановки формы оплаты перевозчика.
        if (! Schema::hasColumn('orders', 'carrier_payment_form')) {
            $orders->loadMissing(array_filter([
                Schema::hasTable('leg_costs') ? 'legs.cost' : null,
                Schema::hasTable('financial_terms') ? 'financialTerms' : null,
            ]));
        }

        $orders->each(function (Order $order): void {
            if (! Schema::hasColumn('orders', 'carrier_payment_form')) {
                $order->setAttribute('carrier_payment_form', CarrierPaymentFormResolver::forOrder($order));
            }
        });

        $totalOrders = $orders->count();
        $directOrders = $orders
            ->filter(fn (Order $order): bool => $this->dealTypeClassifier->classify($order) === 'direct')
            ->count();

        $weeklyClientReturns = $this->weeklyExpectedCustomerIncomingFromSchedule($managerId);

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

    /**
     * @return list<string>
     */
    private function orderSelectColumnsForMetrics(): array
    {
        $candidates = [
            'id',
            'customer_payment_form',
            'carrier_payment_form',
            'delta',
            'order_customer_date',
            'customer_rate',
        ];

        return array_values(array_filter($candidates, fn (string $column): bool => Schema::hasColumn('orders', $column)));
    }

    /**
     * Сумма ожидаемых поступлений от клиентов на текущей календарной неделе по графику оплат (payment_schedules).
     */
    private function weeklyExpectedCustomerIncomingFromSchedule(int $managerId): float
    {
        if (! Schema::hasTable('payment_schedules')) {
            return 0.0;
        }

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $query = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->where('orders.manager_id', $managerId)
            ->where('payment_schedules.party', 'customer')
            ->whereBetween('payment_schedules.planned_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereIn('payment_schedules.status', ['pending', 'overdue']);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        return round((float) $query->sum('payment_schedules.amount'), 2);
    }
}
