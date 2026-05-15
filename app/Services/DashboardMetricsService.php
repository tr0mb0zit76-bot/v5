<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function __construct(
        private readonly CompletedOrderFinancialAnalytics $completedOrderFinancialAnalytics,
    ) {}

    /**
     * @return array{
     *     total_orders:int,
     *     period_delta:float,
     *     weekly_client_returns:float,
     *     weekly_client_returns_overdue:float,
     *     tasks_today:int,
     *     tasks_overdue:int,
     *     plan_completion_percent:float,
     *     tasks_on_time_percent:float,
     *     tasks_sla_breached_open:int,
     *     margin_rank:string,
     *     finance_chart: list<array{ym: string, label: string, income: float, expense: float, margin: float}>,
     *     finance_flow_mode: 'hidden'|'margin_own'|'full'
     * }
     */
    public function forDashboard(User $user, string $dateFrom, string $dateTo): array
    {
        $user->loadMissing('role');
        $managerId = $user->id;
        $tilesScope = RoleAccess::resolveVisibilityScopeForUser($user, 'dashboard_tiles');
        $effectiveManagerId = $tilesScope === 'own' ? $managerId : null;
        $roleName = $user->role?->name;
        $showDualMetrics = $tilesScope === 'all'
            && in_array($roleName, ['admin', 'supervisor'], true);

        $primary = $this->tileMetricsForManager($effectiveManagerId, $dateFrom, $dateTo);
        $finance = $this->financeChartForUser($user, $tilesScope, $managerId, $dateFrom, $dateTo);

        $payload = [
            ...$primary,
            ...$finance,
            'show_dual_metrics' => $showDualMetrics,
            'metrics_scope' => $tilesScope === 'all' ? 'company' : 'own',
            'metrics_own' => null,
        ];

        if ($showDualMetrics) {
            $payload['metrics_own'] = $this->tileMetricsForManager($managerId, $dateFrom, $dateTo);
        }

        return $payload;
    }

    /**
     * @return array{
     *     total_orders:int,
     *     period_delta:float,
     *     weekly_client_returns:float,
     *     weekly_client_returns_overdue:float,
     *     tasks_today:int,
     *     tasks_overdue:int,
     *     plan_completion_percent:float,
     *     tasks_on_time_percent:float,
     *     tasks_sla_breached_open:int,
     *     margin_rank:string
     * }
     */
    private function tileMetricsForManager(?int $managerId, string $dateFrom, string $dateTo): array
    {
        $orderColumns = array_values(array_filter(
            ['id', 'delta'],
            fn (string $column): bool => Schema::hasColumn('orders', $column)
        ));

        $query = Order::query()
            ->when($managerId !== null, fn ($q) => $q->where('manager_id', $managerId))
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            );

        $select = $orderColumns === [] ? ['*'] : $orderColumns;
        $orders = $query->get($select);

        $weeklyReturns = $this->weeklyCustomerReturnDueTotals($managerId);
        $taskMetrics = $this->taskMetricsForManager($managerId, $dateFrom, $dateTo);

        return [
            'total_orders' => $orders->count(),
            'period_delta' => round($orders->sum(fn (Order $order): float => (float) ($order->delta ?? 0)), 2),
            'weekly_client_returns' => round($weeklyReturns['total'], 2),
            'weekly_client_returns_overdue' => round($weeklyReturns['overdue'], 2),
            'tasks_today' => $taskMetrics['tasks_today'],
            'tasks_overdue' => $taskMetrics['tasks_overdue'],
            'plan_completion_percent' => $taskMetrics['plan_completion_percent'],
            'tasks_on_time_percent' => $taskMetrics['tasks_on_time_percent'],
            'tasks_sla_breached_open' => $taskMetrics['tasks_sla_breached_open'],
            'margin_rank' => '—',
        ];
    }

    /**
     * @return array{finance_chart: list<array<string, mixed>>, finance_flow_mode: 'hidden'|'margin_own'|'full'}
     */
    private function financeChartForUser(User $user, string $tilesScope, int $managerId, string $dateFrom, string $dateTo): array
    {
        $roleName = $user->role?->name;
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        if (! in_array($roleName, ['admin', 'supervisor', 'accountant', 'manager'], true)) {
            return [
                'finance_chart' => [],
                'finance_flow_mode' => 'hidden',
            ];
        }

        if ($tilesScope === 'all') {
            return [
                'finance_flow_mode' => 'full',
                'finance_chart' => $this->completedOrderFinancialAnalytics->monthlyBucketsAggregate($from, $to),
            ];
        }

        $raw = $this->completedOrderFinancialAnalytics->monthlyBucketsForManager($managerId, $from, $to);

        return [
            'finance_flow_mode' => 'margin_own',
            'finance_chart' => array_map(static function (array $row): array {
                return [
                    ...$row,
                    'income' => 0.0,
                    'expense' => 0.0,
                ];
            }, $raw),
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
    private function taskMetricsForManager(?int $managerId, string $dateFrom, string $dateTo): array
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
            ->when($managerId !== null, fn ($q) => $q->where('responsible_id', $managerId))
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

        $completedTaskColumns = ['completed_at', 'due_at'];
        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $completedTaskColumns[] = 'sla_deadline_at';
        }

        $completedInPeriod = Task::query()
            ->when($managerId !== null, fn ($q) => $q->where('responsible_id', $managerId))
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->when(
                Schema::hasColumn('tasks', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->get($completedTaskColumns);

        $withDeadline = $completedInPeriod->filter(
            fn (Task $task): bool => $task->due_at !== null || ($task->sla_deadline_at ?? null) !== null
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
     * @return array{total: float, overdue: float}
     */
    private function weeklyCustomerReturnDueTotals(?int $managerId): array
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('payment_schedules', 'planned_date')) {
            return ['total' => 0.0, 'overdue' => 0.0];
        }

        $today = Carbon::today();
        $weekEnd = Carbon::now()->endOfWeek();
        $amountExpr = $this->paymentScheduleOutstandingAmountExpression();

        $base = $this->customerScheduleDueBaseQuery($managerId);

        $overdue = (float) (clone $base)
            ->whereDate('payment_schedules.planned_date', '<', $today)
            ->sum(DB::raw($amountExpr));

        $total = (float) (clone $base)
            ->where(function ($query) use ($today, $weekEnd): void {
                $query->whereDate('payment_schedules.planned_date', '<', $today)
                    ->orWhereBetween('payment_schedules.planned_date', [$today->toDateString(), $weekEnd->toDateString()]);
            })
            ->sum(DB::raw($amountExpr));

        return ['total' => $total, 'overdue' => $overdue];
    }

    private function customerScheduleDueBaseQuery(?int $managerId): Builder
    {
        $query = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->when($managerId !== null, fn ($q) => $q->where('orders.manager_id', $managerId))
            ->where('payment_schedules.party', 'customer')
            ->whereIn('payment_schedules.status', ['pending', 'overdue']);

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('payment_schedules.parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('payment_schedules.is_partial')
                    ->orWhere('payment_schedules.is_partial', false);
            });
        }

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        return $query;
    }

    private function paymentScheduleOutstandingAmountExpression(): string
    {
        if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            return 'CASE WHEN payment_schedules.remaining_amount IS NULL THEN payment_schedules.amount ELSE payment_schedules.remaining_amount END';
        }

        return 'payment_schedules.amount';
    }
}
