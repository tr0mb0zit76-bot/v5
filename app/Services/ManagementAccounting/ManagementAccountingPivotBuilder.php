<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Support\ManagementAccountingPeriodSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingPivotBuilder
{
    public function __construct(
        private readonly ManagementAccountingCategoryBreakdownService $breakdownService,
        private readonly ManagementAccountingOperationalActualsMerger $operationalActualsMerger,
    ) {}

    /**
     * @return array{
     *     columns: list<array{key: string, label: string, start: string, end: string}>,
     *     time_series: list<array{key: string, label: string, revenue: float, expense: float, profit: float}>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function build(
        string $periodType,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $categories,
        array $planByCategory,
    ): array {
        $columns = $this->buildColumns($periodType, $start, $end);
        $leafActuals = $this->aggregateLeafActualsByColumn($columns, $start, $end);
        $revenueByColumn = $this->revenueByColumn($columns, $leafActuals, $categories);
        $rows = $this->buildTreeRows($categories, $columns, $leafActuals, $revenueByColumn, $planByCategory, $start, $end);

        $timeSeries = array_map(function (array $column) use ($leafActuals, $categories, $revenueByColumn): array {
            $key = $column['key'];
            $revenue = (float) ($revenueByColumn[$key] ?? 0);
            $expense = $this->sumExpenseForColumn($leafActuals, $categories, $key);

            return [
                'key' => $key,
                'label' => $column['label'],
                'revenue' => round($revenue, 2),
                'expense' => round($expense, 2),
                'profit' => round($revenue - $expense, 2),
            ];
        }, $columns);

        return [
            'columns' => $columns,
            'time_series' => $timeSeries,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{key: string, label: string, start: string, end: string}>
     */
    private function buildColumns(string $periodType, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $columns = [];

        if ($periodType === ManagementAccountingAnalyticsService::PERIOD_YEAR) {
            $cursor = $start->startOfMonth();
            while ($cursor <= $end) {
                $monthEnd = $cursor->endOfMonth()->min($end);
                $columns[] = [
                    'key' => $cursor->format('Y-m'),
                    'label' => ManagementAccountingPeriodSupport::pivotMonthLabel($cursor),
                    'start' => $cursor->toDateString(),
                    'end' => $monthEnd->toDateString(),
                ];
                $cursor = $cursor->addMonth()->startOfMonth();
            }

            return $columns;
        }

        if ($periodType === ManagementAccountingAnalyticsService::PERIOD_QUARTER) {
            $cursor = $start->startOfWeek(CarbonImmutable::MONDAY);
            if ($cursor < $start) {
                $cursor = $start;
            }

            $weekIndex = 1;
            while ($cursor <= $end) {
                $weekEnd = $cursor->endOfWeek(CarbonImmutable::SUNDAY)->min($end);
                $columns[] = [
                    'key' => 'w'.$weekIndex,
                    'label' => 'Нед '.$weekIndex,
                    'start' => $cursor->toDateString(),
                    'end' => $weekEnd->toDateString(),
                ];
                $cursor = $weekEnd->addDay();
                $weekIndex++;
            }

            return $columns;
        }

        $cursor = $start;
        while ($cursor <= $end) {
            $columns[] = [
                'key' => $cursor->format('Y-m-d'),
                'label' => (string) $cursor->day,
                'start' => $cursor->toDateString(),
                'end' => $cursor->toDateString(),
            ];
            $cursor = $cursor->addDay();
        }

        return $columns;
    }

    /**
     * @param  list<array{key: string, label: string, start: string, end: string}>  $columns
     * @return array<int, array<string, array{in: float, out: float}>>
     */
    private function aggregateLeafActualsByColumn(array $columns, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $byCategory = [];

        if (Schema::hasTable('management_statement_lines')) {
            $lines = ManagementStatementLine::query()
                ->where('status', 'allocated')
                ->whereBetween('operation_date', [$start->toDateString(), $end->toDateString()])
                ->get(['direction', 'amount', 'operation_date', 'allocation_category_id']);

            foreach ($lines as $line) {
                $columnKey = $this->columnKeyForDate($columns, (string) $line->operation_date);
                if ($columnKey === null || $line->allocation_category_id === null) {
                    continue;
                }

                $categoryId = (int) $line->allocation_category_id;
                $byCategory[$categoryId][$columnKey] ??= ['in' => 0.0, 'out' => 0.0];

                if ($line->direction === 'in') {
                    $byCategory[$categoryId][$columnKey]['in'] += (float) $line->amount;
                } elseif ($line->direction === 'out') {
                    $byCategory[$categoryId][$columnKey]['out'] += (float) $line->amount;
                }
            }
        }

        $this->operationalActualsMerger->mergePaymentEventsByColumn($columns, $start, $end, $byCategory);
        $this->operationalActualsMerger->mergeFleetTripsByColumn($columns, $start, $end, $byCategory);

        return $byCategory;
    }

    /**
     * @param  list<array{key: string, start: string, end: string}>  $columns
     */
    private function columnKeyForDate(array $columns, string $date): ?string
    {
        return ManagementAccountingPeriodSupport::columnKeyForDate($columns, $date);
    }

    /**
     * @param  array<int, array<string, array{in: float, out: float}>>  $leafActuals
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @return array<string, float>
     */
    private function revenueByColumn(array $columns, array $leafActuals, Collection $categories): array
    {
        $incomeCategoryIds = $categories
            ->filter(fn (ManagementExpenseCategory $category): bool => ($category->flow ?? 'out') === 'in'
                || in_array($category->kind, ['operational_in'], true))
            ->pluck('id')
            ->all();

        $revenue = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $sum = 0.0;

            foreach ($incomeCategoryIds as $categoryId) {
                $sum += (float) ($leafActuals[$categoryId][$key]['in'] ?? 0);
            }

            $revenue[$key] = $sum;
        }

        return $revenue;
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @param  array<int, array<string, array{in: float, out: float}>>  $leafActuals
     * @param  array<string, float>  $revenueByColumn
     * @param  array<int, float>  $planByCategory
     * @return list<array<string, mixed>>
     */
    private function buildTreeRows(
        Collection $categories,
        array $columns,
        array $leafActuals,
        array $revenueByColumn,
        array $planByCategory,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $byParent = $categories->groupBy(fn (ManagementExpenseCategory $category): int => (int) ($category->parent_id ?? 0));
        $rows = [];

        $walk = function (int $parentKey, int $depth) use (
            &$walk,
            &$rows,
            $byParent,
            $categories,
            $columns,
            $leafActuals,
            $revenueByColumn,
            $planByCategory,
            $start,
            $end,
        ): void {
            $nodes = $byParent->get($parentKey, collect());

            foreach ($nodes as $category) {
                $childIds = $this->collectDescendantIds($categories, $category->id);
                $hasChildren = $categories->where('parent_id', $category->id)->isNotEmpty();

                $cells = $this->rollupCells($category, $childIds, $columns, $leafActuals, $revenueByColumn);
                $totals = $this->rollupTotals($cells, (string) ($category->flow ?? 'out'));

                if ($totals['in'] === 0.0 && $totals['out'] === 0.0 && ! $hasChildren) {
                    $plan = $planByCategory[$category->id] ?? null;
                    if ($plan === null || $plan <= 0.0) {
                        continue;
                    }
                }

                $planAmount = $this->rollupPlan($category->id, $childIds, $planByCategory);

                $row = [
                    'category_id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'kind' => $category->kind,
                    'flow' => $category->flow ?? 'out',
                    'depth' => $depth,
                    'has_children' => $hasChildren,
                    'actual_in' => $totals['in'],
                    'actual_out' => $totals['out'],
                    'plan_amount' => $planAmount,
                    'variance_amount' => $planAmount !== null ? $totals['out'] - $planAmount : null,
                    'cells' => $cells,
                    ...$this->resolveBreakdownPayload($category, $categories, $start, $end),
                ];

                $rows[] = $row;

                if ($hasChildren) {
                    $walk($category->id, $depth + 1);
                }
            }
        };

        $walk(0, 0);

        $this->appendGrossMarginRow($rows, $columns, $revenueByColumn);
        $this->appendProfitRow($rows, $columns, $revenueByColumn);

        return $rows;
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<array{amount: float, percent: float|null}>
     */
    private function rollupCells(
        ManagementExpenseCategory $category,
        array $categoryIds,
        array $columns,
        array $leafActuals,
        array $revenueByColumn,
    ): array {
        $cells = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $in = 0.0;
            $out = 0.0;

            foreach ($categoryIds as $categoryId) {
                $in += (float) ($leafActuals[$categoryId][$key]['in'] ?? 0);
                $out += (float) ($leafActuals[$categoryId][$key]['out'] ?? 0);
            }

            $amount = ($category->flow ?? 'out') === 'in' ? $in : $out;
            $revenue = (float) ($revenueByColumn[$key] ?? 0);
            $percent = $revenue > 0 && ($category->flow ?? 'out') === 'out'
                ? round(($out / $revenue) * 100, 1)
                : null;

            $cells[] = [
                'key' => $key,
                'amount' => round($amount, 2),
                'percent' => $percent,
            ];
        }

        return $cells;
    }

    /**
     * @param  list<array{amount: float, percent: float|null}>  $cells
     * @return array{in: float, out: float}
     */
    private function rollupTotals(array $cells, string $flow): array
    {
        $sum = 0.0;

        foreach ($cells as $cell) {
            $sum += (float) $cell['amount'];
        }

        if ($flow === 'in') {
            return ['in' => round($sum, 2), 'out' => 0.0];
        }

        return ['in' => 0.0, 'out' => round($sum, 2)];
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  array<int, float>  $planByCategory
     */
    private function rollupPlan(int $categoryId, array $categoryIds, array $planByCategory): ?float
    {
        $sum = 0.0;
        $hasPlan = false;

        foreach ($categoryIds as $id) {
            if (! array_key_exists($id, $planByCategory)) {
                continue;
            }

            $hasPlan = true;
            $sum += (float) $planByCategory[$id];
        }

        return $hasPlan ? round($sum, 2) : null;
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @return list<int>
     */
    private function collectDescendantIds(Collection $categories, int $rootId): array
    {
        $ids = [$rootId];
        $children = $categories->where('parent_id', $rootId);

        foreach ($children as $child) {
            $ids = [...$ids, ...$this->collectDescendantIds($categories, $child->id)];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @return array{breakdown: list<array<string, mixed>>, breakdown_label: string}
     */
    private function resolveBreakdownPayload(
        ManagementExpenseCategory $category,
        Collection $categories,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return [
            'breakdown' => [],
            'breakdown_label' => 'none',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string}>  $columns
     * @param  array<string, float>  $revenueByColumn
     */
    private function appendGrossMarginRow(array &$rows, array $columns, array $revenueByColumn): void
    {
        $cells = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $revenue = (float) ($revenueByColumn[$key] ?? 0);
            $cost = $this->sumGroupExpenseForColumn($rows, $key, 'group_cost');
            $grossMargin = $revenue - $cost;

            $cells[] = [
                'key' => $key,
                'amount' => round($grossMargin, 2),
                'percent' => null,
            ];
        }

        $totalRevenue = array_sum($revenueByColumn);
        $totalCost = array_sum(array_map(
            fn (array $column): float => $this->sumGroupExpenseForColumn($rows, $column['key'], 'group_cost'),
            $columns,
        ));

        $rows[] = [
            'category_id' => null,
            'parent_id' => null,
            'code' => 'gross_margin',
            'name' => 'Валовая маржа',
            'kind' => 'summary',
            'flow' => 'neutral',
            'depth' => 0,
            'has_children' => false,
            'actual_in' => 0.0,
            'actual_out' => 0.0,
            'plan_amount' => null,
            'variance_amount' => null,
            'cells' => $cells,
            'breakdown' => [],
            'breakdown_label' => 'none',
            'is_summary' => true,
            'summary_gross_margin' => round($totalRevenue - $totalCost, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sumGroupExpenseForColumn(array $rows, string $columnKey, string $groupCode): float
    {
        foreach ($rows as $row) {
            if (($row['code'] ?? '') !== $groupCode) {
                continue;
            }

            foreach ($row['cells'] as $cell) {
                if (($cell['key'] ?? '') === $columnKey) {
                    return (float) $cell['amount'];
                }
            }
        }

        return 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string}>  $columns
     * @param  array<string, float>  $revenueByColumn
     */
    private function appendProfitRow(array &$rows, array $columns, array $revenueByColumn): void
    {
        $cells = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $revenue = (float) ($revenueByColumn[$key] ?? 0);
            $expense = 0.0;

            foreach ($rows as $row) {
                if (($row['flow'] ?? 'out') !== 'out' || ($row['kind'] ?? '') === 'group') {
                    if (($row['kind'] ?? '') === 'group' && ($row['code'] ?? '') === 'group_expense') {
                        foreach ($row['cells'] as $cell) {
                            if ($cell['key'] === $key) {
                                $expense += (float) $cell['amount'];
                            }
                        }
                    }

                    continue;
                }

                if (($row['parent_id'] ?? null) === null && ($row['kind'] ?? '') !== 'group') {
                    continue;
                }
            }

            $expense = $this->sumExpenseForColumnFromRows($rows, $key);
            $profit = $revenue - $expense;

            $cells[] = [
                'key' => $key,
                'amount' => round($profit, 2),
                'percent' => null,
            ];
        }

        $totalRevenue = array_sum($revenueByColumn);
        $totalExpense = array_sum(array_map(
            fn (array $column): float => $this->sumExpenseForColumnFromRows($rows, $column['key']),
            $columns,
        ));
        $totalProfit = $totalRevenue - $totalExpense;

        $rows[] = [
            'category_id' => null,
            'parent_id' => null,
            'code' => 'profit',
            'name' => 'Прибыль',
            'kind' => 'summary',
            'flow' => 'neutral',
            'depth' => 0,
            'has_children' => false,
            'actual_in' => 0.0,
            'actual_out' => 0.0,
            'plan_amount' => null,
            'variance_amount' => null,
            'cells' => $cells,
            'breakdown' => [],
            'breakdown_label' => 'none',
            'is_summary' => true,
            'summary_profit' => round($totalProfit, 2),
        ];
    }

    /**
     * @param  array<int, array<string, array{in: float, out: float}>>  $leafActuals
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     */
    private function sumExpenseForColumn(array $leafActuals, Collection $categories, string $columnKey): float
    {
        $expense = 0.0;

        foreach ($categories as $category) {
            if (($category->flow ?? 'out') !== 'out') {
                continue;
            }

            $expense += (float) ($leafActuals[$category->id][$columnKey]['out'] ?? 0);
        }

        return $expense;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sumExpenseForColumnFromRows(array $rows, string $columnKey): float
    {
        $expense = 0.0;

        foreach ($rows as $row) {
            if (($row['code'] ?? '') !== 'group_expense') {
                continue;
            }

            foreach ($row['cells'] as $cell) {
                if (($cell['key'] ?? '') === $columnKey) {
                    $expense += (float) $cell['amount'];
                }
            }
        }

        return $expense;
    }
}
