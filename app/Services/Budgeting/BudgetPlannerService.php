<?php

declare(strict_types=1);

namespace App\Services\Budgeting;

/**
 * План маржи: сверху вниз (цели → маржа) или снизу вверх (маржа менеджера → безубыточность).
 */
final class BudgetPlannerService
{
    public const MODE_TOP_DOWN = 'top_down';

    public const MODE_BOTTOM_UP = 'bottom_up';

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'calculation_mode' => self::MODE_TOP_DOWN,
            'horizon_months' => 12,
            'breakeven_month' => 6,
            'cash_zero_month' => 12,
            'target_dividends_month' => 12,
            'target_dividends_amount' => 250_000,
            'owner_investment' => 300_000,
            'manager_count' => 3,
            'margin_per_manager' => null,
            'use_db_margin_per_manager' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeInputs(array $raw): array
    {
        $defaults = self::defaultInputs();
        $mode = ($raw['calculation_mode'] ?? $defaults['calculation_mode']) === self::MODE_BOTTOM_UP
            ? self::MODE_BOTTOM_UP
            : self::MODE_TOP_DOWN;

        $horizon = max(6, min(36, (int) ($raw['horizon_months'] ?? $defaults['horizon_months'])));
        $breakevenMonth = max(1, min($horizon, (int) ($raw['breakeven_month'] ?? $defaults['breakeven_month'])));
        $cashZeroMonth = max(
            $breakevenMonth,
            min($horizon, (int) ($raw['cash_zero_month'] ?? $defaults['cash_zero_month'] ?? $breakevenMonth)),
        );
        $targetMonth = max($cashZeroMonth, min($horizon, (int) ($raw['target_dividends_month'] ?? $defaults['target_dividends_month'])));

        $marginPerManager = $raw['margin_per_manager'] ?? null;
        $marginPerManager = $marginPerManager === null || $marginPerManager === ''
            ? null
            : max(0, (float) $marginPerManager);

        return [
            'calculation_mode' => $mode,
            'horizon_months' => $horizon,
            'breakeven_month' => $breakevenMonth,
            'cash_zero_month' => $cashZeroMonth,
            'target_dividends_month' => $targetMonth,
            'target_dividends_amount' => max(0, (float) ($raw['target_dividends_amount'] ?? $defaults['target_dividends_amount'])),
            'owner_investment' => max(0, (float) ($raw['owner_investment'] ?? $defaults['owner_investment'])),
            'manager_count' => max(1, min(100, (int) ($raw['manager_count'] ?? $defaults['manager_count']))),
            'margin_per_manager' => $marginPerManager,
            'use_db_margin_per_manager' => (bool) ($raw['use_db_margin_per_manager'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $article
     */
    public function articleAppliesToMonth(int $month, array $article): bool
    {
        $rampMonths = $article['ramp_months'] ?? null;

        return $rampMonths === null || $month <= (int) $rampMonths;
    }

    /**
     * @param  list<array<string, mixed>>  $opexArticles
     */
    public function monthlyFixedOpex(int $month, array $opexArticles): float
    {
        $total = 0.0;

        foreach ($opexArticles as $article) {
            if (! $this->articleAppliesToMonth($month, $article)) {
                continue;
            }

            if (($article['cost_type'] ?? 'fixed_monthly') === 'percent_of_margin') {
                continue;
            }

            $total += (float) ($article['amount_monthly'] ?? 0);
        }

        return $total;
    }

    /**
     * Доля маржи (0…1), сумма процентов по активным статьям.
     *
     * @param  list<array<string, mixed>>  $opexArticles
     */
    public function monthlyPercentRate(int $month, array $opexArticles): float
    {
        $rate = 0.0;

        foreach ($opexArticles as $article) {
            if (! $this->articleAppliesToMonth($month, $article)) {
                continue;
            }

            if (($article['cost_type'] ?? 'fixed_monthly') !== 'percent_of_margin') {
                continue;
            }

            $rate += max(0, (float) ($article['percent_of_margin'] ?? 0)) / 100;
        }

        return min(0.99, $rate);
    }

    /**
     * @param  list<array<string, mixed>>  $opexArticles
     */
    public function monthlyOpex(int $month, array $opexArticles, float $margin = 0.0): float
    {
        $fixed = $this->monthlyFixedOpex($month, $opexArticles);
        $percentPart = $margin * $this->monthlyPercentRate($month, $opexArticles);

        return $fixed + $percentPart;
    }

    /**
     * Маржа компании, при которой чистый поток = targetNet (с учётом % от маржи).
     *
     * @param  list<array<string, mixed>>  $opexArticles
     */
    public function marginForTargetNet(int $month, array $opexArticles, float $targetNet): float
    {
        $fixed = $this->monthlyFixedOpex($month, $opexArticles);
        $rate = $this->monthlyPercentRate($month, $opexArticles);

        if ($rate >= 0.99) {
            return 0.0;
        }

        return ($fixed + $targetNet) / (1 - $rate);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array{id?: int, name: string, amount_monthly: float|int|string, ramp_months?: int|null}>  $opexArticles
     * @param  array{company_margin_monthly_avg?: ?float, margin_per_manager_avg?: ?float}|null  $dbBenchmark
     * @return array<string, mixed>
     */
    public function buildPlan(array $inputs, array $opexArticles, ?array $dbBenchmark = null): array
    {
        $inputs = $this->normalizeInputs($inputs);

        return $inputs['calculation_mode'] === self::MODE_BOTTOM_UP
            ? $this->buildBottomUpPlan($inputs, $opexArticles, $dbBenchmark)
            : $this->buildTopDownPlan($inputs, $opexArticles);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array{id?: int, name: string, amount_monthly: float|int|string, ramp_months?: int|null}>  $opexArticles
     * @param  array{company_margin_monthly_avg?: ?float, margin_per_manager_avg?: ?float}|null  $dbBenchmark
     * @return array<string, mixed>
     */
    private function buildBottomUpPlan(array $inputs, array $opexArticles, ?array $dbBenchmark): array
    {
        $horizon = (int) $inputs['horizon_months'];
        $managerCount = (int) $inputs['manager_count'];
        $marginPerManager = $this->resolveMarginPerManager($inputs, $dbBenchmark);
        $companyMargin = $marginPerManager * $managerCount;

        $months = [];
        $cumulative = (float) $inputs['owner_investment'];
        $minCumulative = $cumulative;
        $breakevenOperating = null;
        $dividendsMonth = null;

        for ($month = 1; $month <= $horizon; $month++) {
            $margin = $companyMargin;
            $opex = $this->monthlyOpex($month, $opexArticles, $margin);
            $net = $margin - $opex;
            $cumulative += $net;
            $minCumulative = min($minCumulative, $cumulative);

            if ($breakevenOperating === null && $net >= 0) {
                $breakevenOperating = $month;
            }

            if ($dividendsMonth === null && $net >= (float) $inputs['target_dividends_amount']) {
                $dividendsMonth = $month;
            }

            $months[] = [
                'month' => $month,
                'margin' => round($margin, 2),
                'margin_per_manager' => round($marginPerManager, 2),
                'opex' => round($opex, 2),
                'opex_fixed' => round($this->monthlyFixedOpex($month, $opexArticles), 2),
                'opex_percent' => round($opex - $this->monthlyFixedOpex($month, $opexArticles), 2),
                'net' => round($net, 2),
                'cumulative' => round($cumulative, 2),
            ];
        }

        $managerPlan = $this->managerPlanFromMonths($months, $managerCount, null, $dividendsMonth);
        $cashMonth = $managerPlan['breakeven_month_cash'];
        $beMonth = $cashMonth ?? $breakevenOperating ?? (int) $inputs['breakeven_month'];
        $marginRequiredAtBe = $this->marginForTargetNet($beMonth, $opexArticles, 0.0);
        $cashEstimated = $cashMonth === null ? $this->estimateCashBreakevenMonth($months) : null;

        return [
            'mode' => self::MODE_BOTTOM_UP,
            'months' => $months,
            'manager_plan' => $managerPlan,
            'summary' => [
                'calculation_mode' => self::MODE_BOTTOM_UP,
                'margin_per_manager_used' => round($marginPerManager, 2),
                'company_margin_monthly' => round($companyMargin, 2),
                'breakeven_month_operating' => $managerPlan['breakeven_month_operating'] ?? $breakevenOperating,
                'breakeven_month_cash' => $cashMonth,
                'breakeven_month_cash_estimated' => $cashEstimated,
                'dividends_feasible_month' => $dividendsMonth,
                'plan_milestone_month' => null,
                'required_margin_breakeven' => round($marginRequiredAtBe, 2),
                'manager_floor_y' => $managerPlan['y'],
                'manager_target_x' => round($marginPerManager, 2),
                'target_dividends_month' => (int) $inputs['target_dividends_month'],
                'target_dividends_amount' => (float) $inputs['target_dividends_amount'],
                'owner_investment' => (float) $inputs['owner_investment'],
                'min_cumulative' => round($minCumulative, 2),
                'cumulative_at_horizon' => round($cumulative, 2),
                'manager_count' => $managerCount,
                'breakeven_month' => $cashMonth,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array{id?: int, name: string, amount_monthly: float|int|string, ramp_months?: int|null}>  $opexArticles
     * @return array<string, mixed>
     */
    private function buildTopDownPlan(array $inputs, array $opexArticles): array
    {
        $horizon = (int) $inputs['horizon_months'];
        $operatingMonth = (int) $inputs['breakeven_month'];
        $cashZeroMonth = (int) $inputs['cash_zero_month'];
        $targetMonth = (int) $inputs['target_dividends_month'];
        $managerCount = (int) $inputs['manager_count'];

        $marginAtOperating = $this->marginForTargetNet($operatingMonth, $opexArticles, 0.0);
        $marginAtTarget = $this->marginForTargetNet(
            $targetMonth,
            $opexArticles,
            (float) $inputs['target_dividends_amount'],
        );
        $marginAtCash = $this->resolveMarginAtCashZero(
            $inputs,
            $opexArticles,
            $operatingMonth,
            $cashZeroMonth,
            $targetMonth,
            $marginAtOperating,
            $marginAtTarget,
        );

        $marginAtCash = max($marginAtOperating, $marginAtCash);
        $marginAtTarget = max($marginAtCash, $marginAtTarget);

        $rampKnots = [
            ['month' => $operatingMonth, 'margin' => $marginAtOperating],
            ['month' => $cashZeroMonth, 'margin' => $marginAtCash],
            ['month' => $targetMonth, 'margin' => $marginAtTarget],
        ];

        $months = [];
        $cumulative = (float) $inputs['owner_investment'];
        $minCumulative = $cumulative;

        for ($month = 1; $month <= $horizon; $month++) {
            $margin = $this->marginAtMonthWithKnots($month, $rampKnots);

            $opex = $this->monthlyOpex($month, $opexArticles, $margin);
            $net = $margin - $opex;
            $cumulative += $net;
            $minCumulative = min($minCumulative, $cumulative);

            $months[] = [
                'month' => $month,
                'margin' => round($margin, 2),
                'margin_per_manager' => round($margin / $managerCount, 2),
                'opex' => round($opex, 2),
                'opex_fixed' => round($this->monthlyFixedOpex($month, $opexArticles), 2),
                'opex_percent' => round($opex - $this->monthlyFixedOpex($month, $opexArticles), 2),
                'net' => round($net, 2),
                'cumulative' => round($cumulative, 2),
            ];
        }

        $managerPlan = $this->managerPlanFromMonths($months, $managerCount, $operatingMonth, $targetMonth, $cashZeroMonth);
        $cashEstimated = $managerPlan['breakeven_month_cash'] === null
            ? $this->estimateCashBreakevenMonth($months)
            : null;

        return [
            'mode' => self::MODE_TOP_DOWN,
            'months' => $months,
            'manager_plan' => $managerPlan,
            'summary' => [
                'calculation_mode' => self::MODE_TOP_DOWN,
                'required_margin_breakeven' => round($marginAtOperating, 2),
                'required_margin_cash_zero' => round($marginAtCash, 2),
                'required_margin_target' => round($marginAtTarget, 2),
                'manager_target_x' => round($marginAtTarget / $managerCount, 2),
                'manager_floor_y' => $managerPlan['y'],
                'company_margin_monthly' => null,
                'margin_per_manager_used' => null,
                'breakeven_month_operating' => $managerPlan['breakeven_month_operating'],
                'breakeven_month_cash' => $managerPlan['breakeven_month_cash'],
                'breakeven_month_cash_estimated' => $cashEstimated,
                'plan_milestone_month' => $operatingMonth,
                'cash_zero_month' => $cashZeroMonth,
                'dividends_feasible_month' => $targetMonth,
                'owner_investment' => (float) $inputs['owner_investment'],
                'min_cumulative' => round($minCumulative, 2),
                'cumulative_at_horizon' => round($cumulative, 2),
                'manager_count' => $managerCount,
                'breakeven_month' => $managerPlan['breakeven_month_cash'],
                'target_dividends_month' => $targetMonth,
                'target_dividends_amount' => (float) $inputs['target_dividends_amount'],
            ],
        ];
    }

    /**
     * @param  list<array{month: int, net: float, cumulative: float}>  $months
     */
    private function estimateCashBreakevenMonth(array $months): ?int
    {
        if ($months === []) {
            return null;
        }

        $last = $months[count($months) - 1];

        if ((float) $last['cumulative'] >= 0 || (float) $last['net'] <= 0) {
            return null;
        }

        return (int) $last['month'] + (int) ceil(-((float) $last['cumulative']) / (float) $last['net']);
    }

    /**
     * @param  list<array{month: int, margin: float, margin_per_manager: float, net: float, cumulative: float}>  $months
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     x: float,
     *     y: float,
     *     manager_count: int,
     *     breakeven_month_operating: ?int,
     *     breakeven_month_cash: ?int
     * }
     */
    private function managerPlanFromMonths(
        array $months,
        int $managerCount,
        ?int $planMilestoneMonth = null,
        ?int $targetMonth = null,
        ?int $cashZeroPlanMonth = null,
    ): array {
        $firstOperating = null;
        $firstCash = null;
        $wasInDeficit = false;

        foreach ($months as $row) {
            $month = (int) $row['month'];
            $cumulative = (float) $row['cumulative'];

            if ($firstOperating === null && (float) $row['net'] >= 0) {
                $firstOperating = $month;
            }

            if ($cumulative < 0) {
                $wasInDeficit = true;
            }

            if ($firstCash === null && $wasInDeficit && $cumulative >= 0) {
                $firstCash = $month;
            }
        }

        $rows = [];

        foreach ($months as $row) {
            $month = (int) $row['month'];
            $tags = [];

            if ($planMilestoneMonth !== null && $month === $planMilestoneMonth) {
                $tags[] = 'plan_milestone';
            }

            if ($firstOperating !== null && $month === $firstOperating) {
                $tags[] = 'operating_be';
            }

            if ($firstCash !== null && $month === $firstCash) {
                $tags[] = 'cash_be';
            }

            if ($targetMonth !== null && $month === $targetMonth) {
                $tags[] = 'target';
            }

            $rows[] = [
                'month' => $month,
                'margin_per_manager' => (float) $row['margin_per_manager'],
                'margin_company' => (float) $row['margin'],
                'net_company' => (float) $row['net'],
                'cumulative' => (float) $row['cumulative'],
                'tags' => $tags,
            ];
        }

        $last = $months[count($months) - 1] ?? null;
        $x = $last !== null ? (float) $last['margin_per_manager'] : 0.0;
        $yMonth = $firstCash ?? $firstOperating;
        $yRow = $yMonth !== null
            ? collect($months)->first(fn (array $m): bool => (int) $m['month'] === $yMonth)
            : ($months[0] ?? null);
        $y = $yRow !== null ? (float) $yRow['margin_per_manager'] : 0.0;

        return [
            'rows' => $rows,
            'x' => round($x, 2),
            'y' => round($y, 2),
            'manager_count' => $managerCount,
            'breakeven_month_operating' => $firstOperating,
            'breakeven_month_cash' => $firstCash,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array{company_margin_monthly_avg?: ?float, margin_per_manager_avg?: ?float}|null  $dbBenchmark
     */
    private function resolveMarginPerManager(array $inputs, ?array $dbBenchmark): float
    {
        if ((bool) $inputs['use_db_margin_per_manager'] && $dbBenchmark !== null) {
            $fromDb = $dbBenchmark['margin_per_manager_avg'] ?? null;

            if ($fromDb !== null && $fromDb > 0) {
                return (float) $fromDb;
            }

            $company = $dbBenchmark['company_margin_monthly_avg'] ?? null;

            if ($company !== null && $company > 0) {
                return (float) $company / (int) $inputs['manager_count'];
            }
        }

        $manual = $inputs['margin_per_manager'];

        if ($manual !== null && $manual > 0) {
            return (float) $manual;
        }

        return 0.0;
    }

    /**
     * @param  list<array{month: int, margin: float}>  $knots
     */
    private function marginAtMonthWithKnots(int $month, array $knots): float
    {
        $prevMonth = 0;
        $prevMargin = 0.0;

        foreach ($knots as $knot) {
            $knotMonth = (int) $knot['month'];
            $knotMargin = (float) $knot['margin'];

            if ($month <= $knotMonth) {
                $span = $knotMonth - $prevMonth;

                if ($span <= 0) {
                    return $knotMargin;
                }

                $t = ($month - $prevMonth) / $span;

                return $prevMargin + ($knotMargin - $prevMargin) * $t;
            }

            $prevMonth = $knotMonth;
            $prevMargin = $knotMargin;
        }

        return $prevMargin;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array<string, mixed>>  $opexArticles
     * @param  list<array{month: int, margin: float}>  $knots
     */
    private function simulateCumulativeThroughMonth(
        int $throughMonth,
        array $inputs,
        array $opexArticles,
        array $knots,
    ): float {
        $cumulative = (float) $inputs['owner_investment'];

        for ($month = 1; $month <= $throughMonth; $month++) {
            $margin = $this->marginAtMonthWithKnots($month, $knots);
            $cumulative += $margin - $this->monthlyOpex($month, $opexArticles, $margin);
        }

        return $cumulative;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array<string, mixed>>  $opexArticles
     */
    private function resolveMarginAtCashZero(
        array $inputs,
        array $opexArticles,
        int $operatingMonth,
        int $cashZeroMonth,
        int $targetMonth,
        float $marginAtOperating,
        float $marginAtTarget,
    ): float {
        $floor = max(
            $marginAtOperating,
            $this->marginForTargetNet($cashZeroMonth, $opexArticles, 0.0),
        );

        if ($cashZeroMonth <= $operatingMonth) {
            return $floor;
        }

        $low = $floor;
        $high = max(
            $marginAtTarget,
            $floor * 2,
            $this->marginForTargetNet($cashZeroMonth, $opexArticles, (float) $inputs['target_dividends_amount']),
        );

        for ($i = 0; $i < 64; $i++) {
            $mid = ($low + $high) / 2;
            $knots = [
                ['month' => $operatingMonth, 'margin' => $marginAtOperating],
                ['month' => $cashZeroMonth, 'margin' => $mid],
                ['month' => $targetMonth, 'margin' => $marginAtTarget],
            ];
            $cumulative = $this->simulateCumulativeThroughMonth($cashZeroMonth, $inputs, $opexArticles, $knots);

            if ($cumulative >= 0) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return max($floor, $high);
    }
}
