<?php

namespace App\Services\ManagementAccounting;

use App\Models\BudgetOpexArticle;
use App\Models\BudgetPlanSnapshot;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Services\Budgeting\BudgetPlanSnapshotService;
use App\Services\Budgeting\BudgetVarianceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingAnalyticsService
{
    public function __construct(
        private readonly ManagementAccountingPivotBuilder $pivotBuilder,
        private readonly ManagementAccountingOperationalActualsMerger $operationalActualsMerger,
        private readonly ManagementAccountingTotalsSplitter $totalsSplitter,
        private readonly BudgetPlanSnapshotService $snapshotService,
        private readonly BudgetVarianceService $varianceService,
        private readonly ManagementAccountingFullPictureService $fullPictureService,
    ) {}

    public const PERIOD_MONTH = 'month';

    public const PERIOD_QUARTER = 'quarter';

    public const PERIOD_YEAR = 'year';

    /**
     * @return array{
     *     period_type: string,
     *     period_anchor: string,
     *     period_start: string,
     *     period_end: string,
     *     period_label: string,
     *     totals: array{
     *         actual_in: float,
     *         actual_out: float,
     *         actual_out_cost: float,
     *         actual_out_budget: float,
     *         actual_out_other: float,
     *         net: float,
     *         plan_in: float,
     *         plan_out: float,
     *         plan_net: float,
     *         variance_net: float,
     *         budget_variance: float,
     *         budget_execution_percent: float|null,
     *         gross_margin: float,
     *         gross_margin_percent: float|null,
     *         business_margin_percent: float|null
     *     },
     *     rows: list<array{
     *         category_id: int|null,
     *         code: string|null,
     *         name: string,
     *         kind: string|null,
     *         actual_in: float,
     *         actual_out: float,
     *         plan_amount: float|null,
     *         variance_amount: float|null
     *     }>,
     *     chart: list<array{key: string, label: string, plan: float, fact: float}>,
     *     pivot: array{
     *         columns: list<array{key: string, label: string, start: string, end: string}>,
     *         time_series: list<array{key: string, label: string, revenue: float, expense: float, profit: float}>,
     *         rows: list<array<string, mixed>>
     *     },
     *     plan_available: bool,
     *     plan_source: string,
     *     plan_snapshot: array{
     *         id: int,
     *         period_label: string,
     *         approved_at: string
     *     }|null,
     *     variance_rows: list<array{
     *         category_id: int|null,
     *         code: string|null,
     *         name: string,
     *         kind: string|null,
     *         planned: float,
     *         actual: float,
     *         variance: float,
     *         variance_percent: float|null
     *     }>,
     *     payroll_variance: array{
     *         name: string,
     *         planned: float,
     *         actual_accrued: float,
     *         actual_paid: float,
     *         variance_paid: float,
     *         variance_percent: float|null
     *     }|null
     * }
     */
    public function build(string $periodType, ?string $periodAnchor = null): array
    {
        $periodType = $this->normalizePeriodType($periodType);
        $anchor = $this->resolveAnchor($periodAnchor);
        $bounds = $this->resolveBounds($periodType, $anchor);

        $categories = ManagementExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'code', 'name', 'kind', 'flow', 'include_in_budget']);

        $aggregates = $this->aggregateActuals($bounds['start'], $bounds['end']);
        $planContext = $this->resolvePlanContext($bounds['start'], $bounds['end']);
        $planOut = $planContext['plan_out'];
        $planByCategory = $planContext['plan_by_category'];

        $actualIn = (float) ($aggregates['totals']['in'] ?? 0);
        $actualOut = (float) ($aggregates['totals']['out'] ?? 0);
        $net = $actualIn - $actualOut;

        $splitTotals = $this->totalsSplitter->split(
            $categories,
            $aggregates['by_category'],
            $actualIn,
            $actualOut,
            $planOut,
        );

        $rows = $this->buildRows($categories, $aggregates['by_category'], $planByCategory);
        $planAvailable = Schema::hasTable('budget_opex_articles');
        $pivot = $this->pivotBuilder->build($periodType, $bounds['start'], $bounds['end'], $categories, $planByCategory);

        $varianceRows = [];
        $payrollVariance = null;

        if ($planContext['snapshot'] instanceof BudgetPlanSnapshot) {
            $varianceRows = $this->varianceService->compare(
                $planContext['snapshot'],
                $bounds['start'],
                $bounds['end'],
                $categories,
                $aggregates['by_category'],
            );
            $payrollVariance = $this->varianceService->payrollVariance(
                $planContext['snapshot'],
                $bounds['start'],
                $bounds['end'],
            );
        }

        return [
            'period_type' => $periodType,
            'period_anchor' => $anchor->toDateString(),
            'period_start' => $bounds['start']->toDateString(),
            'period_end' => $bounds['end']->toDateString(),
            'period_label' => $bounds['label'],
            'totals' => [
                'actual_in' => $actualIn,
                'actual_out' => $actualOut,
                'actual_out_cost' => $splitTotals['actual_out_cost'],
                'actual_out_budget' => $splitTotals['actual_out_budget'],
                'actual_out_other' => $splitTotals['actual_out_other'],
                'net' => $net,
                'plan_in' => 0.0,
                'plan_out' => $planOut,
                'plan_net' => 0.0 - $planOut,
                'variance_net' => $net - (0.0 - $planOut),
                'budget_variance' => $splitTotals['budget_variance'],
                'budget_execution_percent' => $splitTotals['budget_execution_percent'],
                'gross_margin' => $splitTotals['gross_margin'],
                'gross_margin_percent' => $splitTotals['gross_margin_percent'],
                'business_margin_percent' => $actualIn > 0
                    ? round(($net / $actualIn) * 100, 1)
                    : null,
            ],
            'rows' => $rows,
            'chart' => [
                [
                    'key' => 'in',
                    'label' => 'Поступления',
                    'plan' => 0.0,
                    'fact' => $actualIn,
                ],
                [
                    'key' => 'out',
                    'label' => 'Расходы (бюджет)',
                    'plan' => $planOut,
                    'fact' => $splitTotals['actual_out_budget'],
                ],
                [
                    'key' => 'net',
                    'label' => 'Чистый поток',
                    'plan' => 0.0 - $planOut,
                    'fact' => $net,
                ],
            ],
            'pivot' => $pivot,
            'plan_available' => $planAvailable,
            'plan_source' => $planContext['source'],
            'plan_snapshot' => $planContext['snapshot_meta'],
            'variance_rows' => $varianceRows,
            'payroll_variance' => $payrollVariance,
            'full_picture' => $this->fullPictureService->build($bounds['start'], $bounds['end']),
        ];
    }

    /**
     * @return array{
     *     source: string,
     *     plan_out: float,
     *     plan_by_category: array<int, float>,
     *     snapshot: BudgetPlanSnapshot|null,
     *     snapshot_meta: array{id: int, period_label: string, approved_at: string}|null
     * }
     */
    private function resolvePlanContext(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $snapshot = $this->snapshotService->resolveSnapshotForPeriod($start, $end);

        if ($snapshot instanceof BudgetPlanSnapshot) {
            return [
                'source' => 'snapshot',
                'plan_out' => $this->snapshotService->totalPlannedOutflow($snapshot, $start, $end),
                'plan_by_category' => $this->snapshotService->plannedByCategoryForPeriod($snapshot, $start, $end),
                'snapshot' => $snapshot,
                'snapshot_meta' => [
                    'id' => $snapshot->id,
                    'period_label' => $snapshot->period_label,
                    'approved_at' => $snapshot->approved_at->toIso8601String(),
                ],
            ];
        }

        if (! Schema::hasTable('budget_opex_articles')) {
            return [
                'source' => 'none',
                'plan_out' => 0.0,
                'plan_by_category' => [],
                'snapshot' => null,
                'snapshot_meta' => null,
            ];
        }

        return [
            'source' => 'live',
            'plan_out' => $this->resolvePlannedOutflow($start, $end),
            'plan_by_category' => $this->resolvePlannedByCategory($start, $end),
            'snapshot' => null,
            'snapshot_meta' => null,
        ];
    }

    public function normalizePeriodType(string $periodType): string
    {
        return in_array($periodType, [self::PERIOD_MONTH, self::PERIOD_QUARTER, self::PERIOD_YEAR], true)
            ? $periodType
            : self::PERIOD_MONTH;
    }

    private function resolveAnchor(?string $periodAnchor): CarbonImmutable
    {
        if ($periodAnchor !== null && $periodAnchor !== '') {
            return CarbonImmutable::parse($periodAnchor)->startOfDay();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, label: string}
     */
    private function resolveBounds(string $periodType, CarbonImmutable $anchor): array
    {
        return match ($periodType) {
            self::PERIOD_QUARTER => [
                'start' => $anchor->startOfQuarter(),
                'end' => $anchor->endOfQuarter(),
                'label' => sprintf('%d квартал %d', $anchor->quarter, $anchor->year),
            ],
            self::PERIOD_YEAR => [
                'start' => $anchor->startOfYear(),
                'end' => $anchor->endOfYear(),
                'label' => (string) $anchor->year,
            ],
            default => [
                'start' => $anchor->startOfMonth(),
                'end' => $anchor->endOfMonth(),
                'label' => $anchor->locale('ru')->translatedFormat('F Y'),
            ],
        };
    }

    /**
     * @return array{
     *     totals: array{in: float, out: float},
     *     by_category: array<int, array{in: float, out: float}>
     * }
     */
    private function aggregateActuals(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $totals = ['in' => 0.0, 'out' => 0.0];
        $byCategory = [];

        if (! Schema::hasTable('management_statement_lines')) {
            return [
                'totals' => $totals,
                'by_category' => $byCategory,
            ];
        }

        $columns = ['direction', 'amount'];
        $hasCategoryColumn = Schema::hasColumn('management_statement_lines', 'allocation_category_id');
        if ($hasCategoryColumn) {
            $columns[] = 'allocation_category_id';
        }

        $lines = ManagementStatementLine::query()
            ->where('status', 'allocated')
            ->whereBetween('operation_date', [$start->toDateString(), $end->toDateString()])
            ->get($columns);

        foreach ($lines as $line) {
            $this->addActualBucket($totals, $byCategory, (string) $line->direction, (float) $line->amount, $hasCategoryColumn ? $line->allocation_category_id : null);
        }

        $this->operationalActualsMerger->mergePaymentEvents($start, $end, $totals, $byCategory);
        $this->operationalActualsMerger->mergeFleetTrips($start, $end, $totals, $byCategory);

        return [
            'totals' => $totals,
            'by_category' => $byCategory,
        ];
    }

    /**
     * @param  array{in: float, out: float}  $totals
     * @param  array<int, array{in: float, out: float}>  $byCategory
     */
    private function addActualBucket(
        array &$totals,
        array &$byCategory,
        string $direction,
        float $amount,
        ?int $categoryId,
    ): void {
        if ($direction === 'in') {
            $totals['in'] += $amount;
        } elseif ($direction === 'out') {
            $totals['out'] += $amount;
        }

        if ($categoryId === null) {
            return;
        }

        if (! isset($byCategory[$categoryId])) {
            $byCategory[$categoryId] = ['in' => 0.0, 'out' => 0.0];
        }

        if ($direction === 'in') {
            $byCategory[$categoryId]['in'] += $amount;
        } elseif ($direction === 'out') {
            $byCategory[$categoryId]['out'] += $amount;
        }
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @param  array<int, array{in: float, out: float}>  $byCategory
     * @return list<array{
     *     category_id: int|null,
     *     code: string|null,
     *     name: string,
     *     kind: string|null,
     *     actual_in: float,
     *     actual_out: float,
     *     plan_amount: float|null,
     *     variance_amount: float|null
     * }>
     */
    /**
     * @param  array<int, float>  $planByCategory
     */
    private function buildRows(Collection $categories, array $byCategory, array $planByCategory): array
    {
        $rows = [];

        foreach ($categories as $category) {
            $bucket = $byCategory[$category->id] ?? ['in' => 0.0, 'out' => 0.0];
            $actualIn = (float) $bucket['in'];
            $actualOut = (float) $bucket['out'];
            $planAmount = array_key_exists($category->id, $planByCategory)
                ? (float) $planByCategory[$category->id]
                : null;

            if ($actualIn === 0.0 && $actualOut === 0.0 && ($planAmount === null || $planAmount <= 0.0)) {
                continue;
            }

            $rows[] = [
                'category_id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'kind' => $category->kind,
                'actual_in' => $actualIn,
                'actual_out' => $actualOut,
                'plan_amount' => $planAmount,
                'variance_amount' => $planAmount !== null ? $actualOut - $planAmount : null,
            ];
        }

        $uncategorizedIn = 0.0;
        $uncategorizedOut = 0.0;
        $knownIds = $categories->pluck('id')->all();

        foreach ($byCategory as $categoryId => $bucket) {
            if (in_array($categoryId, $knownIds, true)) {
                continue;
            }

            $uncategorizedIn += (float) $bucket['in'];
            $uncategorizedOut += (float) $bucket['out'];
        }

        if ($uncategorizedIn > 0 || $uncategorizedOut > 0) {
            $rows[] = [
                'category_id' => null,
                'code' => null,
                'name' => 'Без статьи',
                'kind' => null,
                'actual_in' => $uncategorizedIn,
                'actual_out' => $uncategorizedOut,
                'plan_amount' => null,
                'variance_amount' => null,
            ];
        }

        return $rows;
    }

    private function resolvePlannedOutflow(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! Schema::hasTable('budget_opex_articles')) {
            return 0.0;
        }

        $months = max(1, $start->startOfMonth()->diffInMonths($end->startOfMonth()) + 1);
        $plan = 0.0;

        $articles = $this->budgetArticlesForPlanning();

        foreach ($articles as $article) {
            if ($article->cost_type !== BudgetOpexArticle::COST_FIXED_MONTHLY) {
                continue;
            }

            $plan += (float) $article->amount_monthly * $months;
        }

        return round($plan, 2);
    }

    /**
     * @return array<int, float>
     */
    private function resolvePlannedByCategory(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! Schema::hasTable('budget_opex_articles')
            || ! Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
            return [];
        }

        $months = max(1, $start->startOfMonth()->diffInMonths($end->startOfMonth()) + 1);
        $planByCategory = [];

        $articles = $this->budgetArticlesForPlanning()
            ->whereNotNull('management_expense_category_id');

        foreach ($articles as $article) {
            if ($article->cost_type !== BudgetOpexArticle::COST_FIXED_MONTHLY) {
                continue;
            }

            $categoryId = (int) $article->management_expense_category_id;
            $planByCategory[$categoryId] = ($planByCategory[$categoryId] ?? 0.0)
                + ((float) $article->amount_monthly * $months);
        }

        return $planByCategory;
    }

    /**
     * @return EloquentCollection<int, BudgetOpexArticle>
     */
    private function budgetArticlesForPlanning(): EloquentCollection
    {
        $query = BudgetOpexArticle::query()
            ->with('managementExpenseCategory:id,include_in_budget');

        if (
            Schema::hasColumn('management_expense_categories', 'include_in_budget')
            && Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')
        ) {
            $query
                ->whereNotNull('management_expense_category_id')
                ->whereHas(
                    'managementExpenseCategory',
                    fn ($categoryQuery) => $categoryQuery->where('include_in_budget', true),
                );
        }

        $columns = ['cost_type', 'amount_monthly'];

        if (Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
            $columns[] = 'management_expense_category_id';
        }

        return $query->get($columns);
    }
}
