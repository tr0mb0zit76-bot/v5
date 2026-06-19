<?php

namespace App\Services\Budgeting;

use App\Models\BudgetPlanSnapshot;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementPayrollHalfUser;
use App\Models\ManagementStatementLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BudgetVarianceService
{
    public function __construct(
        private readonly BudgetPlanSnapshotService $snapshotService,
    ) {}

    /**
     * @return list<array{
     *     category_id: int|null,
     *     code: string|null,
     *     name: string,
     *     kind: string|null,
     *     planned: float,
     *     actual: float,
     *     variance: float,
     *     variance_percent: float|null
     * }>
     */
    public function compare(
        BudgetPlanSnapshot $snapshot,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $categories,
        array $actualByCategory,
    ): array {
        $planByCategory = $this->snapshotService->plannedByCategoryForPeriod($snapshot, $start, $end);
        $rows = [];

        foreach ($categories as $category) {
            $planned = (float) ($planByCategory[$category->id] ?? 0.0);
            $actual = (float) ($actualByCategory[$category->id]['out'] ?? 0.0);

            if ($planned <= 0.0 && $actual <= 0.0) {
                continue;
            }

            $rows[] = $this->buildRow(
                $category->id,
                $category->code,
                $category->name,
                $category->kind,
                $planned,
                $actual,
            );
        }

        $knownIds = $categories->pluck('id')->all();

        foreach ($planByCategory as $categoryId => $planned) {
            if (in_array($categoryId, $knownIds, true)) {
                continue;
            }

            $actual = (float) ($actualByCategory[$categoryId]['out'] ?? 0.0);

            if ($planned <= 0.0 && $actual <= 0.0) {
                continue;
            }

            $rows[] = $this->buildRow($categoryId, null, 'Статья #'.$categoryId, null, (float) $planned, $actual);
        }

        usort($rows, fn (array $left, array $right): int => abs($right['variance']) <=> abs($left['variance']));

        return $rows;
    }

    /**
     * @return array{
     *     name: string,
     *     planned: float,
     *     actual_accrued: float,
     *     actual_paid: float,
     *     variance_paid: float,
     *     variance_percent: float|null
     * }|null
     */
    public function payrollVariance(BudgetPlanSnapshot $snapshot, CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        $payrollCategoryId = ManagementExpenseCategory::query()
            ->where('code', 'payroll_managers')
            ->value('id');

        $planned = 0.0;

        if ($payrollCategoryId !== null) {
            $planByCategory = $this->snapshotService->plannedByCategoryForPeriod($snapshot, $start, $end);
            $planned = (float) ($planByCategory[(int) $payrollCategoryId] ?? 0.0);
        }

        $actualPaid = 0.0;
        $actualAccrued = 0.0;

        if (Schema::hasTable('management_payroll_half_users')) {
            $halfUsers = ManagementPayrollHalfUser::query()
                ->whereHas('payrollHalf', function ($query) use ($start, $end): void {
                    $query->whereDate('period_start', '<=', $end->toDateString())
                        ->whereDate('period_end', '>=', $start->toDateString());
                })
                ->get(['accrued_amount', 'paid_amount']);

            foreach ($halfUsers as $row) {
                $actualAccrued += (float) $row->accrued_amount;
                $actualPaid += (float) $row->paid_amount;
            }
        }

        if ($planned <= 0.0 && $actualPaid <= 0.0 && $actualAccrued <= 0.0) {
            return null;
        }

        $variancePaid = $actualPaid - $planned;

        return [
            'name' => 'ФОТ продавцов',
            'planned' => round($planned, 2),
            'actual_accrued' => round($actualAccrued, 2),
            'actual_paid' => round($actualPaid, 2),
            'variance_paid' => round($variancePaid, 2),
            'variance_percent' => $planned > 0 ? round(($variancePaid / $planned) * 100, 1) : null,
        ];
    }

    /**
     * @return array<int, array{in: float, out: float}>
     */
    public function aggregateActualsByCategory(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $byCategory = [];

        if (! Schema::hasTable('management_statement_lines')) {
            return $byCategory;
        }

        $lines = ManagementStatementLine::query()
            ->where('status', 'allocated')
            ->whereBetween('operation_date', [$start->toDateString(), $end->toDateString()])
            ->get(['direction', 'amount', 'allocation_category_id']);

        foreach ($lines as $line) {
            $categoryId = $line->allocation_category_id;

            if ($categoryId === null) {
                continue;
            }

            if (! isset($byCategory[$categoryId])) {
                $byCategory[$categoryId] = ['in' => 0.0, 'out' => 0.0];
            }

            if ($line->direction === 'in') {
                $byCategory[$categoryId]['in'] += (float) $line->amount;
            } elseif ($line->direction === 'out') {
                $byCategory[$categoryId]['out'] += (float) $line->amount;
            }
        }

        return $byCategory;
    }

    /**
     * @return array{
     *     category_id: int|null,
     *     code: string|null,
     *     name: string,
     *     kind: string|null,
     *     planned: float,
     *     actual: float,
     *     variance: float,
     *     variance_percent: float|null
     * }
     */
    private function buildRow(
        ?int $categoryId,
        ?string $code,
        string $name,
        ?string $kind,
        float $planned,
        float $actual,
    ): array {
        $variance = $actual - $planned;

        return [
            'category_id' => $categoryId,
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'planned' => round($planned, 2),
            'actual' => round($actual, 2),
            'variance' => round($variance, 2),
            'variance_percent' => $planned > 0 ? round(($variance / $planned) * 100, 1) : null,
        ];
    }
}
