<?php

declare(strict_types=1);

namespace App\Services\Budgeting;

/**
 * План маржи «сверху вниз»: рамп до безубыточности, затем до целевых дивидендов.
 */
final class BudgetTopDownPlannerService
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        return [
            'horizon_months' => 12,
            'breakeven_month' => 6,
            'target_dividends_month' => 12,
            'target_dividends_amount' => 250_000,
            'owner_investment' => 300_000,
            'office_monthly' => 100_000,
            'accounting_monthly' => 200_000,
            'manager_count' => 3,
            'manager_payroll_monthly' => 75_000,
            'manager_payroll_months' => 3,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeInputs(array $raw): array
    {
        $defaults = self::defaultInputs();

        $horizon = max(6, min(36, (int) ($raw['horizon_months'] ?? $defaults['horizon_months'])));
        $breakevenMonth = max(1, min($horizon, (int) ($raw['breakeven_month'] ?? $defaults['breakeven_month'])));
        $targetMonth = max($breakevenMonth, min($horizon, (int) ($raw['target_dividends_month'] ?? $defaults['target_dividends_month'])));

        return [
            'horizon_months' => $horizon,
            'breakeven_month' => $breakevenMonth,
            'target_dividends_month' => $targetMonth,
            'target_dividends_amount' => max(0, (float) ($raw['target_dividends_amount'] ?? $defaults['target_dividends_amount'])),
            'owner_investment' => max(0, (float) ($raw['owner_investment'] ?? $defaults['owner_investment'])),
            'office_monthly' => max(0, (float) ($raw['office_monthly'] ?? $defaults['office_monthly'])),
            'accounting_monthly' => max(0, (float) ($raw['accounting_monthly'] ?? $defaults['accounting_monthly'])),
            'manager_count' => max(1, min(100, (int) ($raw['manager_count'] ?? $defaults['manager_count']))),
            'manager_payroll_monthly' => max(0, (float) ($raw['manager_payroll_monthly'] ?? $defaults['manager_payroll_monthly'])),
            'manager_payroll_months' => max(0, min($horizon, (int) ($raw['manager_payroll_months'] ?? $defaults['manager_payroll_months']))),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function monthlyOpex(int $month, array $inputs): float
    {
        $opex = (float) $inputs['office_monthly'] + (float) $inputs['accounting_monthly'];

        if ($month <= (int) $inputs['manager_payroll_months']) {
            $opex += (float) $inputs['manager_payroll_monthly'];
        }

        return $opex;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{
     *     months: list<array{month: int, margin: float, opex: float, net: float, cumulative: float}>,
     *     summary: array<string, float|int>
     * }
     */
    public function buildPlan(array $inputs): array
    {
        $inputs = $this->normalizeInputs($inputs);

        $horizon = (int) $inputs['horizon_months'];
        $breakevenMonth = (int) $inputs['breakeven_month'];
        $targetMonth = (int) $inputs['target_dividends_month'];

        $marginAtBreakeven = $this->monthlyOpex($breakevenMonth, $inputs);
        $marginAtTarget = $this->monthlyOpex($targetMonth, $inputs) + (float) $inputs['target_dividends_amount'];

        $months = [];
        $cumulative = (float) $inputs['owner_investment'];
        $minCumulative = $cumulative;

        for ($month = 1; $month <= $horizon; $month++) {
            $margin = $this->interpolateMargin(
                $month,
                $breakevenMonth,
                $targetMonth,
                $marginAtBreakeven,
                $marginAtTarget,
            );

            $opex = $this->monthlyOpex($month, $inputs);
            $net = $margin - $opex;
            $cumulative += $net;
            $minCumulative = min($minCumulative, $cumulative);

            $months[] = [
                'month' => $month,
                'margin' => round($margin, 2),
                'opex' => round($opex, 2),
                'net' => round($net, 2),
                'cumulative' => round($cumulative, 2),
            ];
        }

        $managerCount = (int) $inputs['manager_count'];

        return [
            'months' => $months,
            'summary' => [
                'required_margin_breakeven' => round($marginAtBreakeven, 2),
                'required_margin_target' => round($marginAtTarget, 2),
                'manager_target_x' => round($marginAtTarget / $managerCount, 2),
                'manager_floor_y' => round($marginAtBreakeven / $managerCount, 2),
                'owner_investment' => (float) $inputs['owner_investment'],
                'min_cumulative' => round($minCumulative, 2),
                'cumulative_at_horizon' => round($cumulative, 2),
                'manager_count' => $managerCount,
                'breakeven_month' => $breakevenMonth,
                'target_dividends_month' => $targetMonth,
            ],
        ];
    }

    private function interpolateMargin(
        int $month,
        int $breakevenMonth,
        int $targetMonth,
        float $marginAtBreakeven,
        float $marginAtTarget,
    ): float {
        if ($month <= $breakevenMonth) {
            if ($breakevenMonth <= 1) {
                return $marginAtBreakeven;
            }

            return $marginAtBreakeven * ($month / $breakevenMonth);
        }

        if ($month <= $targetMonth) {
            $span = $targetMonth - $breakevenMonth;

            if ($span <= 0) {
                return $marginAtTarget;
            }

            return $marginAtBreakeven + ($marginAtTarget - $marginAtBreakeven) * (($month - $breakevenMonth) / $span);
        }

        return $marginAtTarget;
    }
}
