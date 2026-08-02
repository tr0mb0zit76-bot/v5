<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\CompletedOrderFinancialAnalytics;
use App\Support\LeadViewAuthorization;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use App\Support\TaskViewAuthorization;
use App\Support\UserDashboardDepartmentScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сводный отчёт по менеджерам: snapshot / period / compare.
 *
 * @phpstan-type TeamReport array{
 *     mode: string,
 *     filters: array<string, mixed>,
 *     columns: list<array{key: string, group: string, label: string, format: string}>,
 *     rows: list<array<string, mixed>>,
 *     totals: array<string, mixed>,
 *     glossary: string,
 *     compare_meta: array{prev_from: string, prev_to: string}|null,
 *     metric_catalog: array{groups: list<array{key: string, label: string}>, defaults: list<string>},
 *     manager_options: list<array{id: int, name: string}>,
 *     department_options: list<array{id: int, name: string}>
 * }
 */
class ManagerTeamReportService
{
    public function __construct(
        private readonly CompletedOrderFinancialAnalytics $completedOrderFinancialAnalytics,
        private readonly LeadProcessReportsService $leadProcessReports,
    ) {}

    /**
     * @param  list<int>  $requestedUserIds
     * @param  list<string>  $requestedMetrics
     * @return TeamReport
     */
    public function build(
        User $viewer,
        string $mode,
        Carbon $from,
        Carbon $to,
        array $requestedUserIds = [],
        ?int $departmentId = null,
        array $requestedMetrics = [],
    ): array {
        $mode = in_array($mode, ManagerTeamMetricCatalog::modes(), true)
            ? $mode
            : ManagerTeamMetricCatalog::MODE_PERIOD;

        $allowedIds = $this->allowedManagerIds($viewer);
        $selectedIds = $this->resolveSelectedManagerIds($viewer, $allowedIds, $requestedUserIds, $departmentId);
        $metricKeys = ManagerTeamMetricCatalog::resolveMetricKeys($mode, $requestedMetrics);
        $columns = ManagerTeamMetricCatalog::columnsFor($metricKeys);

        $managers = $this->managerDirectory($selectedIds);
        $rows = [];

        foreach ($managers as $manager) {
            $rows[] = $this->emptyRow($manager['id'], $manager['name'], $metricKeys);
        }

        $rowsById = [];
        foreach ($rows as $index => $row) {
            $rowsById[(int) $row['manager_id']] = $index;
        }

        if ($mode === ManagerTeamMetricCatalog::MODE_SNAPSHOT) {
            $this->fillSnapshot($viewer, $rows, $rowsById, $selectedIds, $metricKeys);
        } elseif ($mode === ManagerTeamMetricCatalog::MODE_COMPARE) {
            $prev = $this->previousPeriod($from, $to);
            $this->fillPeriod($viewer, $rows, $rowsById, $selectedIds, $metricKeys, $from, $to, false);
            $prevRows = [];
            foreach ($managers as $manager) {
                $prevRows[] = $this->emptyRow($manager['id'], $manager['name'], $metricKeys);
            }
            $prevById = [];
            foreach ($prevRows as $index => $row) {
                $prevById[(int) $row['manager_id']] = $index;
            }
            $this->fillPeriod($viewer, $prevRows, $prevById, $selectedIds, $metricKeys, $prev['from'], $prev['to'], false);
            $this->attachCompare($rows, $prevRows, $metricKeys);
            $compareMeta = [
                'prev_from' => $prev['from']->toDateString(),
                'prev_to' => $prev['to']->toDateString(),
            ];
        } else {
            $this->fillPeriod($viewer, $rows, $rowsById, $selectedIds, $metricKeys, $from, $to, false);
            $compareMeta = null;
        }

        usort($rows, function (array $a, array $b) use ($metricKeys): int {
            $sortKey = in_array('money_closed_margin', $metricKeys, true)
                ? 'money_closed_margin'
                : ($metricKeys[0] ?? 'manager_name');

            if ($sortKey === 'manager_name') {
                return strcmp((string) $a['manager_name'], (string) $b['manager_name']);
            }

            $av = $this->scalarValue($a['metrics'][$sortKey] ?? null);
            $bv = $this->scalarValue($b['metrics'][$sortKey] ?? null);

            return $bv <=> $av;
        });

        return [
            'mode' => $mode,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'user_ids' => $requestedUserIds,
                'department_id' => $departmentId,
                'metrics' => $metricKeys,
            ],
            'columns' => $columns,
            'rows' => array_values($rows),
            'totals' => $this->buildTotals($rows, $metricKeys, $mode === ManagerTeamMetricCatalog::MODE_COMPARE),
            'glossary' => ManagerTeamMetricCatalog::glossaryForMode($mode),
            'compare_meta' => $compareMeta ?? null,
            'metric_catalog' => [
                'groups' => collect(ManagerTeamMetricCatalog::groups())
                    ->filter(fn (array $group): bool => in_array($mode, $group['modes'], true))
                    ->map(fn (array $group, string $key): array => [
                        'key' => $key,
                        'label' => $group['label'],
                    ])
                    ->values()
                    ->all(),
                'defaults' => ManagerTeamMetricCatalog::defaultMetricKeys($mode),
            ],
            'manager_options' => $this->managerDirectory($allowedIds),
            'department_options' => $this->departmentOptions($viewer),
        ];
    }

    /**
     * @return list<int>
     */
    public function allowedManagerIds(User $viewer): array
    {
        if ($viewer->isAdmin()) {
            return $this->candidateManagerIds();
        }

        $orderScope = RoleAccess::resolveVisibilityScopeForUser($viewer, 'orders');
        $leadsScope = RoleAccess::resolveVisibilityScopeForUser($viewer, 'leads');

        if ($orderScope === 'all' || $leadsScope === 'all') {
            return $this->candidateManagerIds();
        }

        if ($orderScope === 'department' || $leadsScope === 'department' || $viewer->isSupervisor()) {
            $ids = UserDashboardDepartmentScope::departmentUserIds($viewer);

            return $ids === [] ? [(int) $viewer->id] : $ids;
        }

        return [(int) $viewer->id];
    }

    /**
     * @param  list<int>  $allowedIds
     * @param  list<int>  $requestedUserIds
     * @return list<int>
     */
    private function resolveSelectedManagerIds(
        User $viewer,
        array $allowedIds,
        array $requestedUserIds,
        ?int $departmentId,
    ): array {
        $allowedFlip = array_flip($allowedIds);
        $ids = $allowedIds;

        if ($departmentId !== null && $departmentId > 0 && Schema::hasTable('department_user')) {
            if (! $this->viewerCanUseDepartment($viewer, $departmentId)) {
                return array_values(array_filter(
                    $allowedIds,
                    fn (int $id): bool => $id === (int) $viewer->id,
                )) ?: [(int) $viewer->id];
            }

            $deptIds = DB::table('department_user')
                ->where('department_id', $departmentId)
                ->pluck('user_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $ids = array_values(array_filter(
                $deptIds,
                fn (int $id): bool => isset($allowedFlip[$id]),
            ));
        }

        if ($requestedUserIds !== []) {
            $requested = array_values(array_filter(
                array_map('intval', $requestedUserIds),
                fn (int $id): bool => $id > 0 && isset($allowedFlip[$id]),
            ));

            if ($departmentId !== null && $departmentId > 0) {
                $deptFlip = array_flip($ids);
                $ids = array_values(array_filter(
                    $requested,
                    fn (int $id): bool => isset($deptFlip[$id]),
                ));
            } else {
                $ids = $requested;
            }
        }

        if ($ids === []) {
            return [(int) $viewer->id];
        }

        sort($ids);

        return array_values(array_unique($ids));
    }

    private function viewerCanUseDepartment(User $viewer, int $departmentId): bool
    {
        if ($viewer->isAdmin()) {
            return true;
        }

        if ($viewer->isSupervisor()) {
            $owned = array_values(array_unique(array_filter([
                $viewer->primaryDepartmentId(),
                ...$viewer->approvalDepartmentIds(),
            ])));

            return in_array($departmentId, $owned, true);
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function candidateManagerIds(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $query = User::query()->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return list<array{id: int, name: string}>
     */
    private function managerDirectory(array $userIds): array
    {
        if ($userIds === [] || ! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?: '—'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function departmentOptions(User $viewer): array
    {
        if (! Schema::hasTable('departments')) {
            return [];
        }

        $query = Department::query()->orderBy('sort_order')->orderBy('name');

        if (Schema::hasColumn('departments', 'is_active')) {
            $query->where('is_active', true);
        }

        if (! $viewer->isAdmin()) {
            $owned = array_values(array_unique(array_filter([
                $viewer->primaryDepartmentId(),
                ...$viewer->approvalDepartmentIds(),
            ])));

            if ($owned === []) {
                return [];
            }

            $query->whereIn('id', $owned);
        }

        return $query->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => (int) $department->id,
                'name' => (string) $department->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $metricKeys
     * @return array{manager_id: int, manager_name: string, metrics: array<string, mixed>}
     */
    private function emptyRow(int $managerId, string $managerName, array $metricKeys): array
    {
        $metrics = [];
        foreach ($metricKeys as $key) {
            $metrics[$key] = null;
        }

        return [
            'manager_id' => $managerId,
            'manager_name' => $managerName,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    private function previousPeriod(Carbon $from, Carbon $to): array
    {
        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->startOfDay()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->startOfDay()->subDays($days - 1)->startOfDay();

        return ['from' => $prevFrom, 'to' => $prevTo];
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function fillSnapshot(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        array $metricKeys,
    ): void {
        $needed = array_flip($metricKeys);

        if (isset($needed['leads_open']) && Schema::hasTable('leads')) {
            $this->applyLeadCounts(
                $viewer,
                $rows,
                $rowsById,
                $managerIds,
                'leads_open',
                function ($query) {
                    $query->whereNotIn('status', ['won', 'lost']);
                },
            );
        }

        if ((isset($needed['leads_stuck']) || isset($needed['leads_sla_overdue'])) && Schema::hasTable('leads')) {
            $this->applyFunnelRisks($viewer, $rows, $rowsById, $managerIds, $metricKeys);
        }

        $orderStatusKeys = array_values(array_filter(
            $metricKeys,
            fn (string $key): bool => str_starts_with($key, 'orders_by_status.'),
        ));

        if (
            ($orderStatusKeys !== [] || isset($needed['orders_open_count']) || isset($needed['money_pipeline_revenue']) || isset($needed['money_pipeline_margin']))
            && Schema::hasTable('orders')
        ) {
            $this->applyOrderSnapshot($viewer, $rows, $rowsById, $managerIds, $metricKeys);
        }

        if ((isset($needed['tasks_open']) || isset($needed['tasks_overdue'])) && Schema::hasTable('tasks')) {
            $this->applyTaskSnapshot($viewer, $rows, $rowsById, $managerIds, $metricKeys);
        }

        $this->zeroFill($rows, $metricKeys);
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function fillPeriod(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        array $metricKeys,
        Carbon $from,
        Carbon $to,
        bool $zeroMissing = true,
    ): void {
        $needed = array_flip($metricKeys);
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        if (isset($needed['leads_created']) && Schema::hasTable('leads')) {
            $this->applyLeadCounts(
                $viewer,
                $rows,
                $rowsById,
                $managerIds,
                'leads_created',
                function ($query) use ($fromDate, $toDate): void {
                    $query->whereBetween('created_at', [$fromDate, $toDate]);
                },
            );
        }

        if ((isset($needed['leads_won']) || isset($needed['leads_lost']) || isset($needed['leads_win_rate'])) && Schema::hasTable('leads')) {
            if (isset($needed['leads_won']) || isset($needed['leads_win_rate'])) {
                $this->applyLeadCounts(
                    $viewer,
                    $rows,
                    $rowsById,
                    $managerIds,
                    'leads_won',
                    function ($query) use ($fromDate, $toDate): void {
                        $query->where('status', 'won')
                            ->whereBetween('updated_at', [$fromDate, $toDate]);
                    },
                );
            }

            if (isset($needed['leads_lost']) || isset($needed['leads_win_rate'])) {
                $this->applyLeadCounts(
                    $viewer,
                    $rows,
                    $rowsById,
                    $managerIds,
                    'leads_lost',
                    function ($query) use ($fromDate, $toDate): void {
                        $query->where('status', 'lost')
                            ->whereBetween('updated_at', [$fromDate, $toDate]);
                    },
                );
            }

            if (isset($needed['leads_win_rate'])) {
                foreach ($rows as &$row) {
                    $won = (int) ($row['metrics']['leads_won'] ?? 0);
                    $lost = (int) ($row['metrics']['leads_lost'] ?? 0);
                    $closed = $won + $lost;
                    $row['metrics']['leads_win_rate'] = $closed > 0
                        ? round(($won / $closed) * 100, 1)
                        : null;
                }
                unset($row);
            }
        }

        if (isset($needed['orders_created']) && Schema::hasTable('orders')) {
            $this->applyOrderCreated($viewer, $rows, $rowsById, $managerIds, $fromDate, $toDate);
        }

        if (
            isset($needed['orders_closed'])
            || isset($needed['money_closed_margin'])
            || isset($needed['money_closed_avg_check'])
            || isset($needed['money_closed_revenue'])
        ) {
            $this->applyClosedMoney($viewer, $rows, $rowsById, $managerIds, $from, $to, $metricKeys);
        }

        if ((isset($needed['tasks_created']) || isset($needed['tasks_done'])) && Schema::hasTable('tasks')) {
            $this->applyTaskPeriod($viewer, $rows, $rowsById, $managerIds, $metricKeys, $fromDate, $toDate);
        }

        if ($zeroMissing) {
            $this->zeroFill($rows, $metricKeys);
        } else {
            $this->zeroFill($rows, array_values(array_filter(
                $metricKeys,
                fn (string $key): bool => $key !== 'leads_win_rate',
            )));
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $current
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $previous
     * @param  list<string>  $metricKeys
     */
    private function attachCompare(array &$current, array $previous, array $metricKeys): void
    {
        $prevById = [];
        foreach ($previous as $row) {
            $prevById[(int) $row['manager_id']] = $row;
        }

        foreach ($current as &$row) {
            $prev = $prevById[(int) $row['manager_id']] ?? null;
            foreach ($metricKeys as $key) {
                $value = $row['metrics'][$key] ?? null;
                $prevValue = $prev['metrics'][$key] ?? null;
                $delta = null;
                $deltaPct = null;

                if ($value !== null && $prevValue !== null) {
                    $delta = round((float) $value - (float) $prevValue, 2);
                    if ((float) $prevValue != 0.0) {
                        $deltaPct = round((((float) $value - (float) $prevValue) / abs((float) $prevValue)) * 100, 1);
                    }
                }

                $row['metrics'][$key] = [
                    'value' => $value,
                    'prev_value' => $prevValue,
                    'delta' => $delta,
                    'delta_pct' => $deltaPct,
                ];
            }
        }
        unset($row);
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  callable(Builder): void  $constrain
     */
    private function applyLeadCounts(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        string $metricKey,
        callable $constrain,
    ): void {
        if (! Schema::hasColumn('leads', 'responsible_id')) {
            return;
        }

        $query = DB::table('leads')
            ->whereIn('responsible_id', $managerIds)
            ->whereNotNull('responsible_id');

        if (Schema::hasColumn('leads', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        LeadViewAuthorization::applyLeadsVisibilityScopeToQuery($query, $viewer);
        $constrain($query);

        $counts = $query
            ->select('responsible_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('responsible_id')
            ->pluck('cnt', 'responsible_id');

        foreach ($managerIds as $managerId) {
            if (! isset($rowsById[$managerId])) {
                continue;
            }
            $rows[$rowsById[$managerId]]['metrics'][$metricKey] = (int) ($counts[$managerId] ?? 0);
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function applyFunnelRisks(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        array $metricKeys,
    ): void {
        $needed = array_flip($metricKeys);
        $report = $this->leadProcessReports->processStageIssues($viewer);
        $stuck = array_fill_keys($managerIds, 0);
        $sla = array_fill_keys($managerIds, 0);

        foreach ($report['rows'] ?? [] as $issue) {
            $responsibleId = (int) ($issue['responsible_id'] ?? 0);
            if ($responsibleId <= 0 || ! isset($stuck[$responsibleId])) {
                continue;
            }

            $flags = $issue['issue_flags'] ?? [];
            if (in_array('stuck', $flags, true)) {
                $stuck[$responsibleId]++;
            }
            if (in_array('due_overdue', $flags, true)) {
                $sla[$responsibleId]++;
            }
        }

        foreach ($managerIds as $managerId) {
            if (! isset($rowsById[$managerId])) {
                continue;
            }
            if (isset($needed['leads_stuck'])) {
                $rows[$rowsById[$managerId]]['metrics']['leads_stuck'] = $stuck[$managerId];
            }
            if (isset($needed['leads_sla_overdue'])) {
                $rows[$rowsById[$managerId]]['metrics']['leads_sla_overdue'] = $sla[$managerId];
            }
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function applyOrderSnapshot(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        array $metricKeys,
    ): void {
        if (! Schema::hasColumn('orders', 'manager_id') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        $statusExpr = Schema::hasColumn('orders', 'manual_status')
            ? 'COALESCE(orders.manual_status, orders.status)'
            : 'orders.status';

        $query = DB::table('orders')
            ->whereIn('orders.manager_id', $managerIds)
            ->whereNotNull('orders.manager_id');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $viewer, 'orders');

        $select = [
            'orders.manager_id',
            DB::raw("{$statusExpr} as effective_status"),
            DB::raw('COUNT(*) as cnt'),
        ];

        if (Schema::hasColumn('orders', 'customer_rate')) {
            $select[] = DB::raw('SUM(COALESCE(orders.customer_rate, 0)) as revenue');
        } else {
            $select[] = DB::raw('SUM(0) as revenue');
        }

        if (Schema::hasColumn('orders', 'delta')) {
            $select[] = DB::raw('SUM(COALESCE(orders.delta, 0)) as margin');
        } else {
            $select[] = DB::raw('SUM(0) as margin');
        }

        $raw = $query
            ->select($select)
            ->groupBy('orders.manager_id', DB::raw($statusExpr))
            ->get();

        $needed = array_flip($metricKeys);

        foreach ($raw as $item) {
            $managerId = (int) $item->manager_id;
            if (! isset($rowsById[$managerId])) {
                continue;
            }

            $status = (string) $item->effective_status;
            $count = (int) $item->cnt;
            $statusKey = 'orders_by_status.'.$status;

            if (isset($needed[$statusKey])) {
                $rows[$rowsById[$managerId]]['metrics'][$statusKey] = (
                    (int) ($rows[$rowsById[$managerId]]['metrics'][$statusKey] ?? 0)
                ) + $count;
            }

            if (in_array($status, ManagerTeamMetricCatalog::PIPELINE_OPEN_STATUSES, true)) {
                if (isset($needed['orders_open_count'])) {
                    $rows[$rowsById[$managerId]]['metrics']['orders_open_count'] = (
                        (int) ($rows[$rowsById[$managerId]]['metrics']['orders_open_count'] ?? 0)
                    ) + $count;
                }
                if (isset($needed['money_pipeline_revenue'])) {
                    $rows[$rowsById[$managerId]]['metrics']['money_pipeline_revenue'] = round(
                        (float) ($rows[$rowsById[$managerId]]['metrics']['money_pipeline_revenue'] ?? 0) + (float) $item->revenue,
                        2,
                    );
                }
                if (isset($needed['money_pipeline_margin'])) {
                    $rows[$rowsById[$managerId]]['metrics']['money_pipeline_margin'] = round(
                        (float) ($rows[$rowsById[$managerId]]['metrics']['money_pipeline_margin'] ?? 0) + (float) $item->margin,
                        2,
                    );
                }
            }
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     */
    private function applyOrderCreated(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        Carbon $from,
        Carbon $to,
    ): void {
        if (! Schema::hasColumn('orders', 'manager_id') || ! Schema::hasColumn('orders', 'order_date')) {
            return;
        }

        $query = DB::table('orders')
            ->whereIn('orders.manager_id', $managerIds)
            ->whereNotNull('orders.manager_id')
            ->whereBetween('orders.order_date', [$from->toDateString(), $to->toDateString()]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $viewer, 'orders');

        $counts = $query
            ->select('orders.manager_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('orders.manager_id')
            ->pluck('cnt', 'manager_id');

        foreach ($managerIds as $managerId) {
            if (! isset($rowsById[$managerId])) {
                continue;
            }
            $rows[$rowsById[$managerId]]['metrics']['orders_created'] = (int) ($counts[$managerId] ?? 0);
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function applyClosedMoney(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        Carbon $from,
        Carbon $to,
        array $metricKeys,
    ): void {
        $needed = array_flip($metricKeys);
        $stats = $this->completedOrderFinancialAnalytics->statsByManagers($from, $to, $viewer);
        $allowed = array_flip($managerIds);

        foreach ($stats as $stat) {
            $managerId = (int) $stat['manager_id'];
            if (! isset($allowed[$managerId], $rowsById[$managerId])) {
                continue;
            }

            if (isset($needed['orders_closed'])) {
                $rows[$rowsById[$managerId]]['metrics']['orders_closed'] = (int) $stat['orders_count'];
            }
            if (isset($needed['money_closed_margin'])) {
                $rows[$rowsById[$managerId]]['metrics']['money_closed_margin'] = (float) $stat['margin'];
            }
            if (isset($needed['money_closed_avg_check'])) {
                $rows[$rowsById[$managerId]]['metrics']['money_closed_avg_check'] = (float) $stat['avg_check'];
            }
            if (isset($needed['money_closed_revenue'])) {
                $count = (int) $stat['orders_count'];
                $avg = (float) $stat['avg_check'];
                $rows[$rowsById[$managerId]]['metrics']['money_closed_revenue'] = round($count * $avg, 2);
            }
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function applyTaskSnapshot(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        array $metricKeys,
    ): void {
        if (! Schema::hasColumn('tasks', 'responsible_id')) {
            return;
        }

        $needed = array_flip($metricKeys);
        $openStatuses = TaskStatus::openStatuses();

        $visibleQuery = Task::query()
            ->whereIn('responsible_id', $managerIds)
            ->whereNotNull('responsible_id')
            ->whereIn('status', $openStatuses);
        TaskViewAuthorization::applyTasksVisibilityScope($visibleQuery, $viewer);

        $openCounts = (clone $visibleQuery)
            ->select('responsible_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('responsible_id')
            ->pluck('cnt', 'responsible_id');

        $overdueCounts = [];
        if (isset($needed['tasks_overdue']) && Schema::hasColumn('tasks', 'due_at')) {
            $overdueCounts = (clone $visibleQuery)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->select('responsible_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('responsible_id')
                ->pluck('cnt', 'responsible_id');
        }

        foreach ($managerIds as $managerId) {
            if (! isset($rowsById[$managerId])) {
                continue;
            }
            if (isset($needed['tasks_open'])) {
                $rows[$rowsById[$managerId]]['metrics']['tasks_open'] = (int) ($openCounts[$managerId] ?? 0);
            }
            if (isset($needed['tasks_overdue'])) {
                $rows[$rowsById[$managerId]]['metrics']['tasks_overdue'] = (int) ($overdueCounts[$managerId] ?? 0);
            }
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  array<int, int>  $rowsById
     * @param  list<int>  $managerIds
     * @param  list<string>  $metricKeys
     */
    private function applyTaskPeriod(
        User $viewer,
        array &$rows,
        array $rowsById,
        array $managerIds,
        array $metricKeys,
        Carbon $from,
        Carbon $to,
    ): void {
        if (! Schema::hasColumn('tasks', 'responsible_id')) {
            return;
        }

        $needed = array_flip($metricKeys);

        if (isset($needed['tasks_created'])) {
            $query = Task::query()
                ->whereIn('responsible_id', $managerIds)
                ->whereNotNull('responsible_id')
                ->whereBetween('created_at', [$from, $to]);
            TaskViewAuthorization::applyTasksVisibilityScope($query, $viewer);

            $counts = $query
                ->select('responsible_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('responsible_id')
                ->pluck('cnt', 'responsible_id');

            foreach ($managerIds as $managerId) {
                if (! isset($rowsById[$managerId])) {
                    continue;
                }
                $rows[$rowsById[$managerId]]['metrics']['tasks_created'] = (int) ($counts[$managerId] ?? 0);
            }
        }

        if (isset($needed['tasks_done']) && Schema::hasColumn('tasks', 'completed_at')) {
            $query = Task::query()
                ->whereIn('responsible_id', $managerIds)
                ->whereNotNull('responsible_id')
                ->where('status', 'done')
                ->whereBetween('completed_at', [$from, $to]);
            TaskViewAuthorization::applyTasksVisibilityScope($query, $viewer);

            $counts = $query
                ->select('responsible_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('responsible_id')
                ->pluck('cnt', 'responsible_id');

            foreach ($managerIds as $managerId) {
                if (! isset($rowsById[$managerId])) {
                    continue;
                }
                $rows[$rowsById[$managerId]]['metrics']['tasks_done'] = (int) ($counts[$managerId] ?? 0);
            }
        }
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  list<string>  $metricKeys
     */
    private function zeroFill(array &$rows, array $metricKeys): void
    {
        foreach ($rows as &$row) {
            foreach ($metricKeys as $key) {
                if (($row['metrics'][$key] ?? null) === null && $key !== 'leads_win_rate') {
                    $row['metrics'][$key] = str_starts_with($key, 'money_') ? 0.0 : 0;
                }
            }
        }
        unset($row);
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, metrics: array<string, mixed>}>  $rows
     * @param  list<string>  $metricKeys
     * @return array<string, mixed>
     */
    private function buildTotals(array $rows, array $metricKeys, bool $compare): array
    {
        $totals = [];

        foreach ($metricKeys as $key) {
            if ($key === 'leads_win_rate' || $key === 'money_closed_avg_check') {
                $totals[$key] = null;

                continue;
            }

            if ($compare) {
                $sumValue = 0.0;
                $sumPrev = 0.0;
                $has = false;
                foreach ($rows as $row) {
                    $cell = $row['metrics'][$key] ?? null;
                    if (! is_array($cell)) {
                        continue;
                    }
                    $has = true;
                    $sumValue += (float) ($cell['value'] ?? 0);
                    $sumPrev += (float) ($cell['prev_value'] ?? 0);
                }
                $delta = $has ? round($sumValue - $sumPrev, 2) : null;
                $deltaPct = ($has && $sumPrev != 0.0)
                    ? round((($sumValue - $sumPrev) / abs($sumPrev)) * 100, 1)
                    : null;
                $totals[$key] = [
                    'value' => $has ? round($sumValue, 2) : 0,
                    'prev_value' => $has ? round($sumPrev, 2) : 0,
                    'delta' => $delta,
                    'delta_pct' => $deltaPct,
                ];

                continue;
            }

            $sum = 0.0;
            foreach ($rows as $row) {
                $sum += (float) ($row['metrics'][$key] ?? 0);
            }
            $totals[$key] = str_starts_with($key, 'money_') ? round($sum, 2) : (int) round($sum);
        }

        if (in_array('leads_win_rate', $metricKeys, true) && ! $compare) {
            $won = (int) ($totals['leads_won'] ?? 0);
            $lost = (int) ($totals['leads_lost'] ?? 0);
            $closed = $won + $lost;
            $totals['leads_win_rate'] = $closed > 0 ? round(($won / $closed) * 100, 1) : null;
        }

        if (in_array('money_closed_avg_check', $metricKeys, true) && ! $compare) {
            $closedOrders = (int) ($totals['orders_closed'] ?? 0);
            $revenue = (float) ($totals['money_closed_revenue'] ?? 0);
            if ($closedOrders > 0 && $revenue <= 0) {
                // revenue may be absent — leave null
                $totals['money_closed_avg_check'] = null;
            } elseif ($closedOrders > 0) {
                $totals['money_closed_avg_check'] = round($revenue / $closedOrders, 2);
            } else {
                $totals['money_closed_avg_check'] = null;
            }
        }

        return $totals;
    }

    private function scalarValue(mixed $cell): float
    {
        if (is_array($cell)) {
            return (float) ($cell['value'] ?? 0);
        }

        return (float) ($cell ?? 0);
    }

    /**
     * Список сущностей под ячейкой отчёта (drill-down).
     *
     * @return array{
     *     metric_key: string,
     *     manager_id: int,
     *     manager_name: string,
     *     label: string,
     *     entity: string,
     *     items: list<array{id: int, number: string, title: string, status: string|null, href: string|null}>
     * }
     */
    public function drillDown(
        User $viewer,
        string $mode,
        string $metricKey,
        int $managerId,
        Carbon $from,
        Carbon $to,
        int $limit = 100,
    ): array {
        $mode = in_array($mode, ManagerTeamMetricCatalog::modes(), true)
            ? $mode
            : ManagerTeamMetricCatalog::MODE_PERIOD;

        $allowed = array_flip($this->allowedManagerIds($viewer));
        if (! isset($allowed[$managerId])) {
            abort(403, 'Нет доступа к данным выбранного менеджера.');
        }

        $defs = collect(ManagerTeamMetricCatalog::definitions())->keyBy('key');
        $def = $defs->get($metricKey);
        if (! is_array($def)) {
            abort(422, 'Неизвестная метрика.');
        }

        $modeForQuery = $mode === ManagerTeamMetricCatalog::MODE_COMPARE
            ? ManagerTeamMetricCatalog::MODE_PERIOD
            : $mode;

        if (! in_array($modeForQuery, $def['modes'], true) && ! in_array($mode, $def['modes'], true)) {
            abort(422, 'Метрика недоступна в этом режиме.');
        }

        // Snapshot metrics stay snapshot even if URL mode is compare by mistake.
        if (in_array(ManagerTeamMetricCatalog::MODE_SNAPSHOT, $def['modes'], true)
            && ! in_array(ManagerTeamMetricCatalog::MODE_PERIOD, $def['modes'], true)) {
            $modeForQuery = ManagerTeamMetricCatalog::MODE_SNAPSHOT;
        }

        $managerName = (string) (User::query()->whereKey($managerId)->value('name') ?: '—');
        $label = (string) ($def['label'] ?? $metricKey);
        $limit = max(1, min(200, $limit));

        [$entity, $items] = $this->drillDownItems($viewer, $modeForQuery, $metricKey, $managerId, $from, $to, $limit);

        return [
            'metric_key' => $metricKey,
            'manager_id' => $managerId,
            'manager_name' => $managerName,
            'label' => $label,
            'entity' => $entity,
            'items' => $items,
        ];
    }

    /**
     * @return array{0: string, 1: list<array{id: int, number: string, title: string, status: string|null, href: string|null}>}
     */
    private function drillDownItems(
        User $viewer,
        string $mode,
        string $metricKey,
        int $managerId,
        Carbon $from,
        Carbon $to,
        int $limit,
    ): array {
        if (str_starts_with($metricKey, 'leads_') || in_array($metricKey, ['leads_stuck', 'leads_sla_overdue'], true)) {
            return ['lead', $this->drillDownLeads($viewer, $mode, $metricKey, $managerId, $from, $to, $limit)];
        }

        if (str_starts_with($metricKey, 'orders_') || str_starts_with($metricKey, 'money_')) {
            return ['order', $this->drillDownOrders($viewer, $mode, $metricKey, $managerId, $from, $to, $limit)];
        }

        if (str_starts_with($metricKey, 'tasks_')) {
            return ['task', $this->drillDownTasks($viewer, $mode, $metricKey, $managerId, $from, $to, $limit)];
        }

        return ['unknown', []];
    }

    /**
     * @return list<array{id: int, number: string, title: string, status: string|null, href: string|null}>
     */
    private function drillDownLeads(
        User $viewer,
        string $mode,
        string $metricKey,
        int $managerId,
        Carbon $from,
        Carbon $to,
        int $limit,
    ): array {
        if (! Schema::hasTable('leads')) {
            return [];
        }

        if (in_array($metricKey, ['leads_stuck', 'leads_sla_overdue'], true)) {
            $report = $this->leadProcessReports->processStageIssues($viewer, LeadProcessReportsService::STUCK_STAGE_DAYS, $managerId);
            $flag = $metricKey === 'leads_sla_overdue' ? 'due_overdue' : 'stuck';

            return collect($report['rows'] ?? [])
                ->filter(fn (array $row): bool => in_array($flag, $row['issue_flags'] ?? [], true)
                    || ($metricKey === 'leads_stuck' && in_array('stuck', $row['issue_flags'] ?? [], true)))
                ->take($limit)
                ->map(fn (array $row): array => [
                    'id' => (int) $row['lead_id'],
                    'number' => (string) ($row['lead_number'] ?? ''),
                    'title' => (string) ($row['lead_title'] ?? ''),
                    'status' => implode(', ', $row['issue_labels'] ?? []),
                    'href' => route('leads.show', $row['lead_id'], false),
                ])
                ->values()
                ->all();
        }

        $query = Lead::query()
            ->where('responsible_id', $managerId)
            ->orderByDesc('id');
        LeadViewAuthorization::applyLeadsVisibilityScope($query, $viewer);

        if ($mode === ManagerTeamMetricCatalog::MODE_SNAPSHOT) {
            if ($metricKey === 'leads_open') {
                $query->whereNotIn('status', ['won', 'lost']);
            }
        } else {
            match ($metricKey) {
                'leads_created' => $query->whereBetween('created_at', [$from, $to]),
                'leads_won' => $query->where('status', 'won')->whereBetween('updated_at', [$from, $to]),
                'leads_lost' => $query->where('status', 'lost')->whereBetween('updated_at', [$from, $to]),
                'leads_win_rate' => $query->whereIn('status', ['won', 'lost'])->whereBetween('updated_at', [$from, $to]),
                default => $query->whereRaw('1 = 0'),
            };
        }

        return $query->limit($limit)->get(['id', 'number', 'title', 'status'])
            ->map(fn (Lead $lead): array => [
                'id' => (int) $lead->id,
                'number' => (string) $lead->number,
                'title' => (string) ($lead->title ?? ''),
                'status' => (string) $lead->status,
                'href' => route('leads.show', $lead, false),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, number: string, title: string, status: string|null, href: string|null}>
     */
    private function drillDownOrders(
        User $viewer,
        string $mode,
        string $metricKey,
        int $managerId,
        Carbon $from,
        Carbon $to,
        int $limit,
    ): array {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'manager_id')) {
            return [];
        }

        $statusExpr = Schema::hasColumn('orders', 'manual_status')
            ? 'COALESCE(orders.manual_status, orders.status)'
            : 'orders.status';

        $query = DB::table('orders')
            ->where('orders.manager_id', $managerId)
            ->whereNotNull('orders.manager_id')
            ->orderByDesc('orders.id');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $viewer, 'orders');

        if ($mode === ManagerTeamMetricCatalog::MODE_SNAPSHOT) {
            if (str_starts_with($metricKey, 'orders_by_status.')) {
                $status = substr($metricKey, strlen('orders_by_status.'));
                $query->whereRaw("{$statusExpr} = ?", [$status]);
            } elseif (in_array($metricKey, ['orders_open_count', 'money_pipeline_revenue', 'money_pipeline_margin'], true)) {
                $placeholders = implode(',', array_fill(0, count(ManagerTeamMetricCatalog::PIPELINE_OPEN_STATUSES), '?'));
                $query->whereRaw("{$statusExpr} in ({$placeholders})", ManagerTeamMetricCatalog::PIPELINE_OPEN_STATUSES);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($metricKey === 'orders_created') {
            $query->whereBetween('orders.order_date', [$from->toDateString(), $to->toDateString()]);
        } elseif (in_array($metricKey, ['orders_closed', 'money_closed_margin', 'money_closed_avg_check', 'money_closed_revenue'], true)) {
            $dateCol = $this->completedOrderFinancialAnalytics->completionDateSql();
            if (Schema::hasColumn('orders', 'manual_status')) {
                $query->whereRaw("COALESCE(orders.manual_status, orders.status) IN ('closed', 'completed')");
            } else {
                $query->whereIn('orders.status', ['closed', 'completed']);
            }
            $query->whereRaw("{$dateCol} between ? and ?", [$from->toDateString(), $to->toDateString()]);
        } else {
            $query->whereRaw('1 = 0');
        }

        $numberCol = Schema::hasColumn('orders', 'order_number') ? 'order_number' : 'id';

        return $query
            ->limit($limit)
            ->get(['orders.id', "orders.{$numberCol} as number", DB::raw("{$statusExpr} as status")])
            ->map(fn (object $order): array => [
                'id' => (int) $order->id,
                'number' => (string) ($order->number ?? $order->id),
                'title' => '',
                'status' => (string) ($order->status ?? ''),
                'href' => route('orders.edit', $order->id, false),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, number: string, title: string, status: string|null, href: string|null}>
     */
    private function drillDownTasks(
        User $viewer,
        string $mode,
        string $metricKey,
        int $managerId,
        Carbon $from,
        Carbon $to,
        int $limit,
    ): array {
        if (! Schema::hasTable('tasks')) {
            return [];
        }

        $query = Task::query()
            ->where('responsible_id', $managerId)
            ->orderByDesc('id');
        TaskViewAuthorization::applyTasksVisibilityScope($query, $viewer);

        if ($mode === ManagerTeamMetricCatalog::MODE_SNAPSHOT) {
            $query->whereIn('status', TaskStatus::openStatuses());
            if ($metricKey === 'tasks_overdue' && Schema::hasColumn('tasks', 'due_at')) {
                $query->whereNotNull('due_at')->where('due_at', '<', now());
            } elseif ($metricKey !== 'tasks_open') {
                $query->whereRaw('1 = 0');
            }
        } elseif ($metricKey === 'tasks_created') {
            $query->whereBetween('created_at', [$from, $to]);
        } elseif ($metricKey === 'tasks_done') {
            $query->where('status', 'done');
            if (Schema::hasColumn('tasks', 'completed_at')) {
                $query->whereBetween('completed_at', [$from, $to]);
            } else {
                $query->whereBetween('updated_at', [$from, $to]);
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->limit($limit)->get(['id', 'number', 'title', 'status'])
            ->map(fn (Task $task): array => [
                'id' => (int) $task->id,
                'number' => (string) $task->number,
                'title' => (string) ($task->title ?? ''),
                'status' => (string) $task->status,
                'href' => route('tasks.show', $task, false),
            ])
            ->all();
    }
}
