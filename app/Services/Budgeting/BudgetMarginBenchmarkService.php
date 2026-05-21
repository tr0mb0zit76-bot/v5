<?php

declare(strict_types=1);

namespace App\Services\Budgeting;

use App\Services\CompletedOrderFinancialAnalytics;
use Carbon\Carbon;

/**
 * Фактическая маржа из закрытых заказов для обратного расчёта.
 */
final class BudgetMarginBenchmarkService
{
    public function __construct(
        private readonly CompletedOrderFinancialAnalytics $analytics,
    ) {}

    /**
     * @return array{
     *     period_months: int,
     *     company_margin_monthly_avg: ?float,
     *     margin_per_manager_avg: ?float,
     *     active_managers_count: int
     * }
     */
    public function lastMonthsSummary(int $months = 6): array
    {
        $months = max(1, min(24, $months));
        $to = Carbon::now()->endOfMonth();
        $from = $to->copy()->subMonths($months - 1)->startOfMonth();

        $buckets = $this->analytics->monthlyBucketsAggregate($from, $to);
        $margins = array_map(static fn (array $row): float => (float) ($row['margin'] ?? 0), $buckets);
        $nonZero = array_values(array_filter($margins, static fn (float $v): bool => $v > 0));
        $companyAvg = $nonZero === [] ? null : array_sum($nonZero) / count($nonZero);

        $managerStats = $this->analytics->statsByManagers($from, $to, null);
        $activeManagers = count($managerStats);
        $perManager = null;

        if ($activeManagers > 0) {
            $totalMargin = array_sum(array_map(static fn (array $r): float => (float) ($r['margin'] ?? 0), $managerStats));

            $perManager = $totalMargin / $activeManagers / max(1, $months);
        }

        return [
            'period_months' => $months,
            'company_margin_monthly_avg' => $companyAvg === null ? null : round($companyAvg, 2),
            'margin_per_manager_avg' => $perManager === null ? null : round($perManager, 2),
            'active_managers_count' => $activeManagers,
        ];
    }
}
