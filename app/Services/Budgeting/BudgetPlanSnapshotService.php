<?php

namespace App\Services\Budgeting;

use App\Models\BudgetOpexArticle;
use App\Models\BudgetPlanSnapshot;
use App\Models\BudgetScenario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BudgetPlanSnapshotService
{
    public function __construct(
        private readonly BudgetPlannerService $planner,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     period_label: string,
     *     period_start: string,
     *     period_end: string,
     *     approved_at: string,
     *     lines_count: int
     * }
     */
    public function freeze(
        BudgetScenario $scenario,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        string $periodLabel,
        User $approver,
        ?string $notes = null,
    ): array {
        $scenario->loadMissing([]);

        $inputs = $this->planner->normalizeInputs($scenario->inputs ?? []);
        $articles = $this->resolveArticlesForFreeze();

        return DB::transaction(function () use (
            $scenario,
            $periodStart,
            $periodEnd,
            $periodLabel,
            $approver,
            $notes,
            $articles,
        ): array {
            $snapshot = BudgetPlanSnapshot::query()->create([
                'scenario_id' => $scenario->id,
                'period_label' => $periodLabel,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'approved_at' => now(),
                'approved_by_user_id' => $approver->id,
                'notes' => $notes,
            ]);

            $linesCount = 0;
            $monthIndex = 1;

            for ($cursor = $periodStart->startOfMonth(); $cursor->lte($periodEnd); $cursor = $cursor->addMonth()) {
                foreach ($articles as $article) {
                    $articleRow = $this->articleToPlannerRow($article);

                    if (! $this->planner->articleAppliesToMonth($monthIndex, $articleRow)) {
                        continue;
                    }

                    if ($article->cost_type !== BudgetOpexArticle::COST_FIXED_MONTHLY) {
                        continue;
                    }

                    $plannedAmount = round((float) $article->amount_monthly, 2);

                    if ($plannedAmount <= 0) {
                        continue;
                    }

                    $snapshot->lines()->create([
                        'month' => $cursor->toDateString(),
                        'opex_article_id' => $article->id,
                        'category_id' => $article->management_expense_category_id,
                        'article_name' => $article->managementExpenseCategory?->name ?? $article->name,
                        'planned_amount' => $plannedAmount,
                    ]);

                    $linesCount++;
                }

                $monthIndex++;
            }

            return [
                'id' => $snapshot->id,
                'period_label' => $snapshot->period_label,
                'period_start' => $snapshot->period_start->toDateString(),
                'period_end' => $snapshot->period_end->toDateString(),
                'approved_at' => $snapshot->approved_at->toIso8601String(),
                'lines_count' => $linesCount,
            ];
        });
    }

    public function resolveSnapshotForPeriod(CarbonImmutable $start, CarbonImmutable $end): ?BudgetPlanSnapshot
    {
        if (! Schema::hasTable('budget_plan_snapshots')) {
            return null;
        }

        return BudgetPlanSnapshot::query()
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->where('approved_at', '<=', $end->endOfDay())
            ->orderByDesc('approved_at')
            ->first();
    }

    /**
     * @return array<int, float>
     */
    public function plannedByCategoryForPeriod(BudgetPlanSnapshot $snapshot, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $planByCategory = [];

        $lines = $snapshot->lines()
            ->whereBetween('month', [$start->startOfMonth()->toDateString(), $end->endOfMonth()->toDateString()])
            ->get(['category_id', 'planned_amount']);

        foreach ($lines as $line) {
            if ($line->category_id === null) {
                continue;
            }

            $categoryId = (int) $line->category_id;
            $planByCategory[$categoryId] = ($planByCategory[$categoryId] ?? 0.0) + (float) $line->planned_amount;
        }

        return $planByCategory;
    }

    public function totalPlannedOutflow(BudgetPlanSnapshot $snapshot, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return round((float) $snapshot->lines()
            ->whereBetween('month', [$start->startOfMonth()->toDateString(), $end->endOfMonth()->toDateString()])
            ->sum('planned_amount'), 2);
    }

    /**
     * @return list<array{
     *     id: int,
     *     period_label: string,
     *     period_start: string,
     *     period_end: string,
     *     approved_at: string,
     *     approved_by_name: string|null,
     *     lines_count: int
     * }>
     */
    public function recentSnapshots(int $limit = 5): array
    {
        if (! Schema::hasTable('budget_plan_snapshots')) {
            return [];
        }

        return BudgetPlanSnapshot::query()
            ->with(['approvedBy:id,name'])
            ->withCount('lines')
            ->orderByDesc('approved_at')
            ->limit($limit)
            ->get()
            ->map(fn (BudgetPlanSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'period_label' => $snapshot->period_label,
                'period_start' => $snapshot->period_start->toDateString(),
                'period_end' => $snapshot->period_end->toDateString(),
                'approved_at' => $snapshot->approved_at->toIso8601String(),
                'approved_by_name' => $snapshot->approvedBy?->name,
                'lines_count' => (int) $snapshot->lines_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, BudgetOpexArticle>
     */
    private function resolveArticlesForFreeze()
    {
        $query = BudgetOpexArticle::query()
            ->with('managementExpenseCategory:id,name,include_in_budget')
            ->orderBy('sort_order')
            ->orderBy('id');

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

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function articleToPlannerRow(BudgetOpexArticle $article): array
    {
        return [
            'cost_type' => $article->cost_type ?? BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_monthly' => (float) $article->amount_monthly,
            'percent_of_margin' => $article->percent_of_margin !== null ? (float) $article->percent_of_margin : null,
            'ramp_months' => $article->ramp_months,
        ];
    }
}
