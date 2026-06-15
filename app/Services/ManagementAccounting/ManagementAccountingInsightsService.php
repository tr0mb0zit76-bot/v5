<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class ManagementAccountingInsightsService
{
    public function __construct(
        private readonly ManagementAccountingAnalyticsService $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function insights(User $user, string $periodType = 'month', ?string $periodAnchor = null, int $watchlistLimit = 8): array
    {
        if (! RoleAccess::canAccessManagementAccounting($user)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к управленческому учёту.',
            ];
        }

        $periodType = $this->analytics->normalizePeriodType($periodType);
        $watchlistLimit = max(3, min(15, $watchlistLimit));

        $current = $this->analytics->build($periodType, $periodAnchor);
        $prior = $this->buildPriorPeriodAnalytics($periodType, (string) $current['period_anchor']);

        $totals = $current['totals'];
        $priorTotals = $prior['totals'] ?? [];
        $revenue = (float) ($totals['actual_in'] ?? 0);
        $priorRevenue = (float) ($priorTotals['actual_in'] ?? 0);

        $expenseMix = $this->buildExpenseMix($current['rows'], $revenue, $watchlistLimit);
        $planFactWatchlist = $this->buildPlanFactWatchlist($current['rows'], $watchlistLimit);
        $reconciliation = $this->buildReconciliationHealth($user);
        $periodComparison = $this->buildPeriodComparison($totals, $priorTotals);
        $grossMargin = $this->resolveGrossMarginAmount($current['pivot']['rows'] ?? []);

        $riskFlags = $this->buildRiskFlags(
            $totals,
            $periodComparison,
            $expenseMix,
            $planFactWatchlist,
            $reconciliation,
            $current['plan_available'] ?? false,
        );

        $recommendations = $this->buildRecommendations(
            $totals,
            $periodComparison,
            $expenseMix,
            $planFactWatchlist,
            $reconciliation,
            $riskFlags,
        );

        return [
            'available' => true,
            'period' => [
                'type' => $current['period_type'],
                'anchor' => $current['period_anchor'],
                'start' => $current['period_start'],
                'end' => $current['period_end'],
                'label' => $current['period_label'],
            ],
            'prior_period' => [
                'label' => $prior['period_label'] ?? null,
                'start' => $prior['period_start'] ?? null,
                'end' => $prior['period_end'] ?? null,
            ],
            'executive_headline' => $this->buildExecutiveHeadline($totals, $periodComparison, $grossMargin, $reconciliation),
            'kpis' => [
                'revenue' => $revenue,
                'expense' => (float) ($totals['actual_out'] ?? 0),
                'net_cash_flow' => (float) ($totals['net'] ?? 0),
                'business_margin_percent' => $totals['business_margin_percent'] ?? null,
                'gross_margin' => $grossMargin,
                'gross_margin_percent' => $revenue > 0 && $grossMargin !== null
                    ? round(($grossMargin / $revenue) * 100, 1)
                    : null,
                'planned_expense' => (float) ($totals['plan_out'] ?? 0),
                'actual_out_budget' => (float) ($totals['actual_out_budget'] ?? 0),
                'actual_out_cost' => (float) ($totals['actual_out_cost'] ?? 0),
                'budget_variance' => (float) ($totals['budget_variance'] ?? 0),
                'expense_plan_variance' => (float) ($totals['budget_variance'] ?? 0),
                'net_vs_plan' => (float) ($totals['variance_net'] ?? 0),
                'plan_available' => (bool) ($current['plan_available'] ?? false),
            ],
            'period_comparison' => $periodComparison,
            'expense_mix' => $expenseMix,
            'plan_fact_watchlist' => $planFactWatchlist,
            'reconciliation_health' => $reconciliation,
            'risk_flags' => $riskFlags,
            'recommendations' => $recommendations,
            'suggested_analyst_questions' => $this->suggestedQuestions($riskFlags, $reconciliation, $current['plan_available'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPriorPeriodAnalytics(string $periodType, string $periodAnchor): array
    {
        $anchor = CarbonImmutable::parse($periodAnchor)->startOfDay();

        $priorAnchor = match ($periodType) {
            ManagementAccountingAnalyticsService::PERIOD_QUARTER => $anchor->subQuarter()->toDateString(),
            ManagementAccountingAnalyticsService::PERIOD_YEAR => $anchor->subYear()->toDateString(),
            default => $anchor->subMonth()->toDateString(),
        };

        return $this->analytics->build($periodType, $priorAnchor);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function buildExpenseMix(array $rows, float $revenue, int $limit): array
    {
        if ($revenue <= 0) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $out = (float) ($row['actual_out'] ?? 0);

            if ($out <= 0) {
                continue;
            }

            $items[] = [
                'category_id' => $row['category_id'] ?? null,
                'code' => $row['code'] ?? null,
                'name' => (string) ($row['name'] ?? 'Статья'),
                'amount' => round($out, 2),
                'share_of_revenue_pct' => round(($out / $revenue) * 100, 1),
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function buildPlanFactWatchlist(array $rows, int $limit): array
    {
        $items = [];

        foreach ($rows as $row) {
            if (! array_key_exists('plan_amount', $row) || $row['plan_amount'] === null) {
                continue;
            }

            $plan = (float) $row['plan_amount'];
            $factOut = (float) ($row['actual_out'] ?? 0);
            $variance = (float) ($row['variance_amount'] ?? ($factOut - $plan));

            if ($plan <= 0 && $factOut <= 0) {
                continue;
            }

            $items[] = [
                'category_id' => $row['category_id'] ?? null,
                'code' => $row['code'] ?? null,
                'name' => (string) ($row['name'] ?? 'Статья'),
                'plan' => round($plan, 2),
                'fact' => round($factOut, 2),
                'variance' => round($variance, 2),
                'variance_pct' => $plan > 0 ? round(($variance / $plan) * 100, 1) : null,
            ];
        }

        usort($items, fn (array $a, array $b): int => abs($b['variance']) <=> abs($a['variance']));

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  array<string, float|int|null>  $totals
     * @param  array<string, mixed>  $priorTotals
     * @return array<string, mixed>
     */
    private function buildPeriodComparison(array $totals, array $priorTotals): array
    {
        return [
            'revenue_delta' => $this->delta((float) ($totals['actual_in'] ?? 0), (float) ($priorTotals['actual_in'] ?? 0)),
            'expense_delta' => $this->delta((float) ($totals['actual_out'] ?? 0), (float) ($priorTotals['actual_out'] ?? 0)),
            'net_delta' => $this->delta((float) ($totals['net'] ?? 0), (float) ($priorTotals['net'] ?? 0)),
            'margin_delta_pp' => $this->marginDeltaPoints(
                $totals['business_margin_percent'] ?? null,
                $priorTotals['business_margin_percent'] ?? null,
            ),
        ];
    }

    /**
     * @return array{absolute: float, percent: float|null}
     */
    private function delta(float $current, float $prior): array
    {
        $absolute = round($current - $prior, 2);

        return [
            'absolute' => $absolute,
            'percent' => $prior != 0.0 ? round(($absolute / abs($prior)) * 100, 1) : null,
        ];
    }

    private function marginDeltaPoints(mixed $current, mixed $prior): ?float
    {
        if (! is_numeric($current) || ! is_numeric($prior)) {
            return null;
        }

        return round((float) $current - (float) $prior, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $pivotRows
     */
    private function resolveGrossMarginAmount(array $pivotRows): ?float
    {
        foreach ($pivotRows as $row) {
            if (($row['code'] ?? '') !== 'gross_margin') {
                continue;
            }

            if (array_key_exists('summary_gross_margin', $row)) {
                return round((float) $row['summary_gross_margin'], 2);
            }

            return null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReconciliationHealth(User $user): array
    {
        if (! Schema::hasTable('management_statement_lines') || ! Schema::hasTable('management_statement_imports')) {
            return [
                'available' => false,
                'pending_lines' => 0,
                'pending_amount' => 0.0,
                'incomplete_imports' => 0,
            ];
        }

        $importQuery = ManagementStatementImport::query();

        if (! $user->isAdmin()) {
            $importQuery->where('imported_by', $user->id);
        }

        $importIds = $importQuery->pluck('id');

        $pendingQuery = ManagementStatementLine::query()
            ->where('status', 'pending');

        if ($importIds->isNotEmpty()) {
            $pendingQuery->whereIn('import_id', $importIds);
        } elseif (! $user->isAdmin()) {
            $pendingQuery->whereRaw('1 = 0');
        }

        $pendingLines = (clone $pendingQuery)->count();
        $pendingAmount = (float) (clone $pendingQuery)->sum('amount');

        $incompleteImports = ManagementStatementImport::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('imported_by', $user->id))
            ->whereColumn('lines_allocated', '<', 'lines_count')
            ->count();

        $lowConfidence = 0;

        if (Schema::hasColumn('management_statement_lines', 'match_confidence')) {
            $lowConfidenceQuery = ManagementStatementLine::query()
                ->where('status', 'pending')
                ->whereNotNull('match_confidence')
                ->where('match_confidence', '<', 0.6);

            if ($importIds->isNotEmpty()) {
                $lowConfidenceQuery->whereIn('import_id', $importIds);
            } elseif (! $user->isAdmin()) {
                $lowConfidenceQuery->whereRaw('1 = 0');
            }

            $lowConfidence = $lowConfidenceQuery->count();
        }

        return [
            'available' => true,
            'pending_lines' => $pendingLines,
            'pending_amount' => round($pendingAmount, 2),
            'incomplete_imports' => $incompleteImports,
            'low_confidence_pending' => $lowConfidence,
        ];
    }

    /**
     * @param  array<string, float|int|null>  $totals
     * @param  array<string, mixed>  $periodComparison
     * @param  list<array<string, mixed>>  $expenseMix
     * @param  list<array<string, mixed>>  $planFactWatchlist
     * @param  array<string, mixed>  $reconciliation
     * @return list<array{severity: string, code: string, message: string}>
     */
    private function buildRiskFlags(
        array $totals,
        array $periodComparison,
        array $expenseMix,
        array $planFactWatchlist,
        array $reconciliation,
        bool $planAvailable,
    ): array {
        $flags = [];

        $net = (float) ($totals['net'] ?? 0);
        $margin = $totals['business_margin_percent'] ?? null;

        if ($net < 0) {
            $flags[] = [
                'severity' => 'high',
                'code' => 'negative_net_cash_flow',
                'message' => 'Чистый денежный поток за период отрицательный — расходы превышают поступления.',
            ];
        }

        if (is_numeric($margin) && (float) $margin < 5) {
            $flags[] = [
                'severity' => 'high',
                'code' => 'thin_business_margin',
                'message' => 'Маржинальность бизнеса ниже 5% — мало запаса на операционные расходы и налоги.',
            ];
        }

        $revenueDeltaPct = $periodComparison['revenue_delta']['percent'] ?? null;
        if (is_numeric($revenueDeltaPct) && (float) $revenueDeltaPct <= -15) {
            $flags[] = [
                'severity' => 'medium',
                'code' => 'revenue_decline',
                'message' => 'Поступления существенно ниже предыдущего аналогичного периода.',
            ];
        }

        $expenseDeltaPct = $periodComparison['expense_delta']['percent'] ?? null;
        if (is_numeric($expenseDeltaPct) && (float) $expenseDeltaPct >= 20) {
            $flags[] = [
                'severity' => 'medium',
                'code' => 'expense_growth',
                'message' => 'Расходы растут быстрее, чем в предыдущем периоде.',
            ];
        }

        if ($planAvailable) {
            $planOut = (float) ($totals['plan_out'] ?? 0);
            $actualOut = (float) ($totals['actual_out'] ?? 0);

            if ($planOut > 0 && $actualOut > $planOut * 1.15) {
                $flags[] = [
                    'severity' => 'medium',
                    'code' => 'opex_over_plan',
                    'message' => 'Фактические расходы заметно выше плана бюджета (OPEX).',
                ];
            }
        }

        $topExpense = $expenseMix[0] ?? null;
        if (is_array($topExpense) && ($topExpense['share_of_revenue_pct'] ?? 0) >= 45) {
            $flags[] = [
                'severity' => 'medium',
                'code' => 'expense_concentration',
                'message' => sprintf(
                    'Высокая концентрация расходов: «%s» — %.1f%% поступлений.',
                    $topExpense['name'],
                    (float) $topExpense['share_of_revenue_pct'],
                ),
            ];
        }

        if (($reconciliation['pending_lines'] ?? 0) > 0) {
            $flags[] = [
                'severity' => ($reconciliation['pending_lines'] ?? 0) >= 20 ? 'high' : 'medium',
                'code' => 'unallocated_bank_lines',
                'message' => sprintf(
                    'В выписках %d неразнесённых операций на %.0f ₽ — искажают картину факта.',
                    (int) $reconciliation['pending_lines'],
                    (float) ($reconciliation['pending_amount'] ?? 0),
                ),
            ];
        }

        foreach (array_slice($planFactWatchlist, 0, 3) as $item) {
            $variancePct = $item['variance_pct'] ?? null;

            if (is_numeric($variancePct) && abs((float) $variancePct) >= 25) {
                $flags[] = [
                    'severity' => 'low',
                    'code' => 'category_plan_variance',
                    'message' => sprintf(
                        'Статья «%s»: отклонение от плана %.1f%%.',
                        $item['name'],
                        (float) $variancePct,
                    ),
                ];
            }
        }

        return $flags;
    }

    /**
     * @param  array<string, float|int|null>  $totals
     * @param  array<string, mixed>  $periodComparison
     * @param  list<array<string, mixed>>  $expenseMix
     * @param  list<array<string, mixed>>  $planFactWatchlist
     * @param  array<string, mixed>  $reconciliation
     * @param  list<array<string, mixed>>  $riskFlags
     * @return list<string>
     */
    private function buildRecommendations(
        array $totals,
        array $periodComparison,
        array $expenseMix,
        array $planFactWatchlist,
        array $reconciliation,
        array $riskFlags,
    ): array {
        $items = [];

        if (($reconciliation['pending_lines'] ?? 0) > 0) {
            $items[] = 'Закройте хвост разнесения выписки: без этого P&L и cash flow неполные. Начните с крупных pending-операций и правил по ключевым словам.';
        }

        if (($totals['net'] ?? 0) < 0) {
            $items[] = 'Разложите отрицательный поток: себестоимость рейсов vs ФОТ vs АУР. Сравните доли с предыдущим периодом и планом.';
        }

        if ($expenseMix !== []) {
            $leader = $expenseMix[0];
            $items[] = sprintf(
                'Проверьте драйвер «%s» (%.1f%% поступлений): обоснован ли рост и есть ли рычаги оптимизации без потери качества.',
                $leader['name'],
                (float) $leader['share_of_revenue_pct'],
            );
        }

        if ($planFactWatchlist !== []) {
            $worst = $planFactWatchlist[0];
            $items[] = sprintf(
                'Сфокусируйтесь на отклонении план/факт по «%s» (Δ %.0f ₽) — уточните разовые vs структурные причины.',
                $worst['name'],
                (float) $worst['variance'],
            );
        }

        $marginDelta = $periodComparison['margin_delta_pp'] ?? null;
        if (is_numeric($marginDelta) && (float) $marginDelta <= -3) {
            $items[] = 'Маржинальность бизнеса просела к прошлому периоду — проверьте ставки, себестоимость рейсов и долю постоянных расходов.';
        }

        if ($riskFlags === []) {
            $items[] = 'Критичных сигналов нет — используйте отчёт по статьям для контроля тренда и сверки с бюджетом.';
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  array<string, float|int|null>  $totals
     * @param  array<string, mixed>  $periodComparison
     * @param  array<string, mixed>  $reconciliation
     */
    private function buildExecutiveHeadline(
        array $totals,
        array $periodComparison,
        ?float $grossMargin,
        array $reconciliation,
    ): string {
        $revenue = (float) ($totals['actual_in'] ?? 0);
        $net = (float) ($totals['net'] ?? 0);
        $margin = $totals['business_margin_percent'] ?? null;

        $parts = [];

        if ($revenue > 0) {
            $parts[] = sprintf('Поступления %.0f ₽', $revenue);
        }

        if ($grossMargin !== null) {
            $parts[] = sprintf('валовая маржа %.0f ₽', $grossMargin);
        }

        if (is_numeric($margin)) {
            $parts[] = sprintf('маржинальность бизнеса %.1f%%', (float) $margin);
        }

        $parts[] = sprintf('чистый поток %.0f ₽', $net);

        $revenueTrend = $periodComparison['revenue_delta']['percent'] ?? null;
        if (is_numeric($revenueTrend)) {
            $parts[] = sprintf('выручка к пред. периоду %+.1f%%', (float) $revenueTrend);
        }

        if (($reconciliation['pending_lines'] ?? 0) > 0) {
            $parts[] = sprintf('%d операций выписки не разнесено', (int) $reconciliation['pending_lines']);
        }

        return implode('; ', $parts).'.';
    }

    /**
     * @param  list<array<string, mixed>>  $riskFlags
     * @return list<string>
     */
    private function suggestedQuestions(array $riskFlags, array $reconciliation, bool $planAvailable): array
    {
        $questions = [
            'Дай executive summary за текущий период: выручка, маржа, главные риски.',
            'Какие статьи расходов больше всего съедают поступления?',
        ];

        if ($planAvailable) {
            $questions[] = 'Где самые большие отклонения план/факт по статьям?';
        }

        if (($reconciliation['pending_lines'] ?? 0) > 0) {
            $questions[] = 'Что мешает закрыть разнесение выписки и какие операции разобрать в первую очередь?';
        }

        if (collect($riskFlags)->contains(fn (array $flag): bool => $flag['code'] === 'negative_net_cash_flow')) {
            $questions[] = 'Почему чистый поток отрицательный и что сократить в первую очередь?';
        }

        return array_slice($questions, 0, 5);
    }
}
