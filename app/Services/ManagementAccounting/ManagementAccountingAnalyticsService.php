<?php

namespace App\Services\ManagementAccounting;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingAnalyticsService
{
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
     *         net: float,
     *         plan_in: float,
     *         plan_out: float,
     *         plan_net: float,
     *         variance_net: float
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
     *     plan_available: bool
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
            ->get(['id', 'code', 'name', 'kind']);

        $aggregates = $this->aggregateActuals($bounds['start'], $bounds['end']);
        $planOut = $this->resolvePlannedOutflow($bounds['start'], $bounds['end']);

        $actualIn = (float) ($aggregates['totals']['in'] ?? 0);
        $actualOut = (float) ($aggregates['totals']['out'] ?? 0);
        $net = $actualIn - $actualOut;

        $rows = $this->buildRows($categories, $aggregates['by_category']);
        $planAvailable = Schema::hasTable('budget_opex_articles');

        return [
            'period_type' => $periodType,
            'period_anchor' => $anchor->toDateString(),
            'period_start' => $bounds['start']->toDateString(),
            'period_end' => $bounds['end']->toDateString(),
            'period_label' => $bounds['label'],
            'totals' => [
                'actual_in' => $actualIn,
                'actual_out' => $actualOut,
                'net' => $net,
                'plan_in' => 0.0,
                'plan_out' => $planOut,
                'plan_net' => 0.0 - $planOut,
                'variance_net' => $net - (0.0 - $planOut),
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
                    'label' => 'Расходы',
                    'plan' => $planOut,
                    'fact' => $actualOut,
                ],
                [
                    'key' => 'net',
                    'label' => 'Чистый поток',
                    'plan' => 0.0 - $planOut,
                    'fact' => $net,
                ],
            ],
            'plan_available' => $planAvailable,
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
            $amount = (float) $line->amount;
            $direction = (string) $line->direction;

            if ($direction === 'in') {
                $totals['in'] += $amount;
            } elseif ($direction === 'out') {
                $totals['out'] += $amount;
            }

            if (! $hasCategoryColumn) {
                continue;
            }

            $categoryId = $line->allocation_category_id;
            if ($categoryId === null) {
                continue;
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

        return [
            'totals' => $totals,
            'by_category' => $byCategory,
        ];
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
    private function buildRows(Collection $categories, array $byCategory): array
    {
        $rows = [];

        foreach ($categories as $category) {
            $bucket = $byCategory[$category->id] ?? ['in' => 0.0, 'out' => 0.0];
            $actualIn = (float) $bucket['in'];
            $actualOut = (float) $bucket['out'];

            if ($actualIn === 0.0 && $actualOut === 0.0) {
                continue;
            }

            $rows[] = [
                'category_id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'kind' => $category->kind,
                'actual_in' => $actualIn,
                'actual_out' => $actualOut,
                'plan_amount' => null,
                'variance_amount' => null,
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

        $articles = BudgetOpexArticle::query()->get(['cost_type', 'amount_monthly']);

        foreach ($articles as $article) {
            if ($article->cost_type !== BudgetOpexArticle::COST_FIXED_MONTHLY) {
                continue;
            }

            $plan += (float) $article->amount_monthly * $months;
        }

        return round($plan, 2);
    }
}
