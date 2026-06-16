<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\Disposition\DispositionKpiService;
use App\Support\PaymentScheduleSettlementStatus;
use App\Support\RoleAccess;
use App\Support\UserDashboardDepartmentScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function __construct(
        private readonly CompletedOrderFinancialAnalytics $completedOrderFinancialAnalytics,
        private readonly DispositionKpiService $dispositionKpi,
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
        $user->loadMissing(['role', 'departments']);
        $managerId = (int) $user->id;
        $tilesScope = RoleAccess::resolveVisibilityScopeForUser($user, 'dashboard_tiles');
        $metricsScope = $this->resolveMetricsScope($user, $tilesScope);
        $managerFilter = $this->resolveManagerFilter($user, $metricsScope);
        $roleName = $user->role?->name;
        $showDualMetrics = in_array($metricsScope, ['company', 'department'], true)
            && in_array($roleName, ['admin', 'supervisor'], true);

        $primary = $this->tileMetricsForScope($managerFilter, $dateFrom, $dateTo);
        $finance = $this->financeChartForUser($user, $metricsScope === 'company' ? 'all' : 'own', $managerId, $dateFrom, $dateTo);

        $payload = [
            ...$primary,
            ...$finance,
            'show_dual_metrics' => $showDualMetrics,
            'metrics_scope' => $metricsScope,
            'metrics_own' => null,
        ];

        if ($showDualMetrics) {
            $payload['metrics_own'] = $this->tileMetricsForScope(['mode' => 'managers', 'ids' => [$managerId]], $dateFrom, $dateTo);
        }

        $dispositionKpi = $this->dispositionKpiPayload($user, $showDualMetrics, $managerId);
        if ($dispositionKpi !== null) {
            $payload = [...$payload, ...$dispositionKpi];
        }

        return $payload;
    }

    private function resolveMetricsScope(User $user, string $tilesScope): string
    {
        if ($user->isAdmin()) {
            return 'company';
        }

        if ($user->seesCompanyDashboard()) {
            return 'company';
        }

        if ($tilesScope === 'all') {
            return 'company';
        }

        if ($tilesScope === 'department') {
            return 'department';
        }

        return 'own';
    }

    /**
     * @return array{mode: 'all'|'managers', ids?: list<int>}
     */
    private function resolveManagerFilter(User $user, string $metricsScope): array
    {
        return match ($metricsScope) {
            'company' => ['mode' => 'all'],
            'department' => [
                'mode' => 'managers',
                'ids' => UserDashboardDepartmentScope::departmentUserIds($user),
            ],
            default => [
                'mode' => 'managers',
                'ids' => [(int) $user->id],
            ],
        };
    }

    /**
     * @return array{disposition_kpi: array<string, mixed>, disposition_kpi_own: ?array<string, mixed>}|null
     */
    private function dispositionKpiPayload(User $user, bool $showDualMetrics, int $managerId): ?array
    {
        if (! $this->dispositionKpi->userSeesDashboardWidget($user)) {
            return null;
        }

        $payload = [
            'disposition_kpi' => $this->dispositionKpi->metricsForUser($user),
            'disposition_kpi_own' => null,
        ];

        if ($showDualMetrics) {
            $payload['disposition_kpi_own'] = $this->dispositionKpi->metricsForUser($user, null, true);
        }

        return $payload;
    }

    /**
     * @param  array{mode: 'all'|'managers', ids?: list<int>}  $managerFilter
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
    private function tileMetricsForScope(array $managerFilter, string $dateFrom, string $dateTo): array
    {
        $orderColumns = array_values(array_filter(
            ['id', 'delta'],
            fn (string $column): bool => Schema::hasColumn('orders', $column)
        ));

        $query = Order::query()
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            );

        $this->applyManagerFilter($query, $managerFilter, 'manager_id');

        $select = $orderColumns === [] ? ['*'] : $orderColumns;
        $orders = $query->get($select);

        $weeklyReturns = $this->weeklyCustomerReturnDueTotals($managerFilter);
        $taskMetrics = $this->taskMetricsForScope($managerFilter, $dateFrom, $dateTo);

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
     * @param  EloquentBuilder<Order>|Builder  $query
     * @param  array{mode: 'all'|'managers', ids?: list<int>}  $managerFilter
     */
    private function applyManagerFilter(EloquentBuilder|Builder $query, array $managerFilter, string $managerColumn = 'manager_id'): void
    {
        if (($managerFilter['mode'] ?? 'all') === 'all') {
            return;
        }

        $managerIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $managerFilter['ids'] ?? [],
        )));

        if ($managerIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (count($managerIds) === 1) {
            $query->where($managerColumn, $managerIds[0]);

            return;
        }

        $query->whereIn($managerColumn, $managerIds);
    }

    /**
     * @return array{finance_chart: list<array<string, mixed>>, finance_flow_mode: 'hidden'|'margin_own'|'full'}
     */
    private function financeChartForUser(User $user, string $tilesScope, int $managerId, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        if (! $this->userCanSeeFinanceFlow($user)) {
            return [
                'finance_chart' => [],
                'finance_flow_mode' => 'hidden',
            ];
        }

        if ($this->userSeesCompanyFinanceFlow($user, $tilesScope)) {
            return [
                'finance_flow_mode' => 'full',
                'finance_chart' => $this->completedOrderFinancialAnalytics->monthlyBucketsAggregate($from, $to),
            ];
        }

        return [
            'finance_flow_mode' => 'margin_own',
            'finance_chart' => $this->completedOrderFinancialAnalytics->monthlyBucketsForManager($managerId, $from, $to),
        ];
    }

    private function userCanSeeFinanceFlow(User $user): bool
    {
        $areas = RoleAccess::userVisibilityAreas($user);

        if (! RoleAccess::hasVisibilityArea($areas, 'dashboard')) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor() || $user->hasRole('accountant')) {
            return true;
        }

        foreach (['manager', 'clerk', 'dispatcher', 'viewer'] as $roleName) {
            if ($user->hasRole($roleName)) {
                return true;
            }
        }

        return RoleAccess::hasVisibilityArea($areas, 'orders')
            || RoleAccess::hasVisibilityArea($areas, 'reports')
            || RoleAccess::hasVisibilityArea($areas, 'payment_schedules');
    }

    private function userSeesCompanyFinanceFlow(User $user, string $tilesScope): bool
    {
        if ($tilesScope !== 'all') {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor() || $user->hasRole('accountant')) {
            return true;
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'reports');
    }

    /**
     * @param  array{mode: 'all'|'managers', ids?: list<int>}  $managerFilter
     * @return array{
     *     tasks_today:int,
     *     tasks_overdue:int,
     *     plan_completion_percent:float,
     *     tasks_on_time_percent:float,
     *     tasks_sla_breached_open:int
     * }
     */
    private function taskMetricsForScope(array $managerFilter, string $dateFrom, string $dateTo): array
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
            ->when(
                Schema::hasColumn('tasks', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            );

        $this->applyTaskManagerFilter($base, $managerFilter);

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
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->when(
                Schema::hasColumn('tasks', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            );

        $this->applyTaskManagerFilter($completedInPeriod, $managerFilter);

        $completedInPeriod = $completedInPeriod->get($completedTaskColumns);

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
     * @param  EloquentBuilder<Task>  $query
     * @param  array{mode: 'all'|'managers', ids?: list<int>}  $managerFilter
     */
    private function applyTaskManagerFilter(EloquentBuilder $query, array $managerFilter): void
    {
        if (($managerFilter['mode'] ?? 'all') === 'all') {
            return;
        }

        $managerIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $managerFilter['ids'] ?? [],
        )));

        if ($managerIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (count($managerIds) === 1) {
            $query->where('responsible_id', $managerIds[0]);

            return;
        }

        $query->whereIn('responsible_id', $managerIds);
    }

    /**
     * @param  array{mode: 'all'|'managers', ids?: list<int>}  $managerFilter
     * @return array{total: float, overdue: float}
     */
    private function weeklyCustomerReturnDueTotals(array $managerFilter): array
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('payment_schedules', 'planned_date')) {
            return ['total' => 0.0, 'overdue' => 0.0];
        }

        $today = Carbon::today();
        $weekEnd = Carbon::now()->endOfWeek();
        $amountExpr = $this->paymentScheduleOutstandingAmountExpression();

        $base = $this->customerScheduleDueBaseQuery($managerFilter);

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

    /**
     * @param  array{mode: 'all'|'managers', ids?: list<int>}  $managerFilter
     */
    private function customerScheduleDueBaseQuery(array $managerFilter): Builder
    {
        $query = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->where('payment_schedules.party', 'customer')
            ->whereIn('payment_schedules.status', ['pending', 'overdue']);

        $this->applyManagerFilter($query, $managerFilter, 'orders.manager_id');

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

        PaymentScheduleSettlementStatus::applyUnsettledRootScope($query);

        return $query;
    }

    private function paymentScheduleOutstandingAmountExpression(): string
    {
        return PaymentScheduleSettlementStatus::outstandingAmountSql();
    }
}
