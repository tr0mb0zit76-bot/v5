<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Models\ManagementStatementLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class CompanyInitiativeBudgetFactService
{
    /**
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     planned_amount: float|null,
     *     fact_out_amount: float,
     *     variance_amount: float|null,
     *     usage_percent: float|null,
     *     category_id: int,
     *     currency: string
     * }|null
     */
    public function snapshot(CompanyInitiative $initiative): ?array
    {
        $categoryId = $initiative->management_expense_category_id;
        if ($categoryId === null) {
            return null;
        }

        $start = CarbonImmutable::parse(
            $initiative->starts_on?->toDateString() ?? now()->startOfYear()->toDateString(),
        )->startOfDay();
        $end = CarbonImmutable::parse(
            $initiative->ends_on?->toDateString() ?? now()->endOfYear()->toDateString(),
        )->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $factOut = $this->sumAllocatedOutflow((int) $categoryId, $start, $end);
        $planned = $initiative->planned_budget_amount !== null
            ? (float) $initiative->planned_budget_amount
            : null;

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'planned_amount' => $planned,
            'fact_out_amount' => round($factOut, 2),
            'variance_amount' => $planned !== null ? round($factOut - $planned, 2) : null,
            'usage_percent' => $planned !== null && $planned > 0
                ? round(($factOut / $planned) * 100, 1)
                : null,
            'category_id' => (int) $categoryId,
            'currency' => strtoupper((string) ($initiative->budget_currency ?? 'RUB')),
        ];
    }

    private function sumAllocatedOutflow(int $categoryId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! Schema::hasTable('management_statement_lines')
            || ! Schema::hasColumn('management_statement_lines', 'allocation_category_id')) {
            return 0.0;
        }

        return (float) ManagementStatementLine::query()
            ->where('status', 'allocated')
            ->where('direction', 'out')
            ->where('allocation_category_id', $categoryId)
            ->whereBetween('operation_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }
}
