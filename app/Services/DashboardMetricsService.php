<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Task;
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
     *     tasks_on_time_percent:float,
     *     tasks_sla_breached_open:int,
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
        $taskMetrics = $this->taskMetricsForManager($managerId, $dateFrom, $dateTo);

        return [
            'total_orders' => $totalOrders,
            'direct_orders' => $directOrders,
            'direct_share_percent' => $totalOrders > 0 ? round(($directOrders / $totalOrders) * 100, 2) : 0.0,
            'period_delta' => round($orders->sum(fn (Order $order): float => (float) ($order->delta ?? 0)), 2),
            'weekly_client_returns' => round($weeklyClientReturns, 2),
            'tasks_today' => $taskMetrics['tasks_today'],
            'tasks_overdue' => $taskMetrics['tasks_overdue'],
            'plan_completion_percent' => $taskMetrics['plan_completion_percent'],
            'tasks_on_time_percent' => $taskMetrics['tasks_on_time_percent'],
            'tasks_sla_breached_open' => $taskMetrics['tasks_sla_breached_open'],
            'margin_rank' => '—',
        ];
    }

    /**
     * @return array{
     *     tasks_today:int,
     *     tasks_overdue:int,
     *     plan_completion_percent:float,
     *     tasks_on_time_percent:float,
     *     tasks_sla_breached_open:int
     * }
     */
    private function taskMetricsForManager(int $managerId, string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('tasks')) {
            return [
                'tasks_today' => 0,
                'tasks_overdue' => 0,
                'plan_completion_percent' => 0.0,
                'tasks_on_time_percent' => 0.0,
                'tasks_sla_breached_open' => 0,
            ];
        }

        $today = Carbon::today();
        $now = Carbon::now();

        $base = Task::query()
            ->where('responsible_id', $managerId)
            ->when(
                Schema::hasColumn('tasks', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            );

        $open = (clone $base)->where('status', '!=', 'done');

        $tasksToday = (clone $open)->where(function ($query) use ($today): void {
            $query->whereDate('due_at', $today);
            if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
                $query->orWhereDate('sla_deadline_at', $today);
            }
        })->count();

        $tasksOverdue = (clone $open)->where(function ($query) use ($now): void {
            $query->where(function ($q) use ($now): void {
                $q->whereNotNull('due_at')->where('due_at', '<', $now);
            });
            if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
                $query->orWhere(function ($q) use ($now): void {
                    $q->whereNotNull('sla_deadline_at')->where('sla_deadline_at', '<', $now);
                });
            }
        })->count();

        $tasksSlaBreachedOpen = 0;
        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $tasksSlaBreachedOpen = (clone $open)
                ->whereNotNull('sla_deadline_at')
                ->where('sla_deadline_at', '<', $now)
                ->count();
        }

        $periodStart = Carbon::parse($dateFrom)->startOfDay();
        $periodEnd = Carbon::parse($dateTo)->endOfDay();

        $completedInPeriod = Task::query()
            ->where('responsible_id', $managerId)
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->when(
                Schema::hasColumn('tasks', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->get(['completed_at', 'due_at', 'sla_deadline_at']);

        $withDeadline = $completedInPeriod->filter(
            fn (Task $task): bool => $task->due_at !== null || $task->sla_deadline_at !== null
        );

        $planCompletionPercent = 0.0;
        $onTimePercent = 0.0;

        if ($withDeadline->isNotEmpty()) {
            $onTime = $withDeadline->filter(function (Task $task): bool {
                if ($task->completed_at === null) {
                    return false;
                }

                $deadline = $task->sla_deadline_at ?? $task->due_at;
                if ($deadline === null) {
                    return false;
                }

                return $task->completed_at->lte($deadline);
            })->count();

            $planCompletionPercent = round(($onTime / $withDeadline->count()) * 100, 2);
            $onTimePercent = $planCompletionPercent;
        }

        return [
            'tasks_today' => $tasksToday,
            'tasks_overdue' => $tasksOverdue,
            'plan_completion_percent' => $planCompletionPercent,
            'tasks_on_time_percent' => $onTimePercent,
            'tasks_sla_breached_open' => $tasksSlaBreachedOpen,
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
