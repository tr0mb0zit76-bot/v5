<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * M4.6 — раздельный снимок операционного контура (график оплат) и управленческого (разнесённые строки).
 */
class ManagementAccountingFullPictureService
{
    public function __construct(
        private readonly ManagementAccountingOperationalActualsMerger $operationalActualsMerger,
    ) {}

    /**
     * @return array{
     *     operational: array{in: float, out: float, net: float},
     *     management: array{in: float, out: float, net: float},
     *     combined: array{in: float, out: float, net: float},
     *     rows: list<array{source: string, source_label: string, category_id: int|null, name: string, in: float, out: float}>
     * }
     */
    public function build(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $managementTotals = ['in' => 0.0, 'out' => 0.0];
        $managementByCategory = [];
        $operationalTotals = ['in' => 0.0, 'out' => 0.0];
        $operationalByCategory = [];

        if (Schema::hasTable('management_statement_lines')) {
            $columns = ['direction', 'amount'];
            $hasCategoryColumn = Schema::hasColumn('management_statement_lines', 'allocation_category_id');

            if ($hasCategoryColumn) {
                $columns[] = 'allocation_category_id';
            }

            ManagementStatementLine::query()
                ->where('status', 'allocated')
                ->whereBetween('operation_date', [$start->toDateString(), $end->toDateString()])
                ->get($columns)
                ->each(function (ManagementStatementLine $line) use (&$managementTotals, &$managementByCategory, $hasCategoryColumn): void {
                    $this->addBucket(
                        $managementTotals,
                        $managementByCategory,
                        (string) $line->direction,
                        (float) $line->amount,
                        $hasCategoryColumn ? $line->allocation_category_id : null,
                    );
                });
        }

        $this->operationalActualsMerger->mergePaymentEvents($start, $end, $operationalTotals, $operationalByCategory);
        $this->operationalActualsMerger->mergeFleetTrips($start, $end, $operationalTotals, $operationalByCategory);

        $categories = Schema::hasTable('management_expense_categories')
            ? ManagementExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code'])
                ->keyBy('id')
            : collect();

        $rows = [];

        foreach ($operationalByCategory as $categoryId => $bucket) {
            if (($bucket['in'] ?? 0) <= 0 && ($bucket['out'] ?? 0) <= 0) {
                continue;
            }

            $category = $categories->get($categoryId);

            $rows[] = [
                'source' => 'operational',
                'source_label' => 'Операционный',
                'category_id' => $categoryId,
                'name' => $category?->name ?? 'Операционные платежи',
                'in' => round((float) ($bucket['in'] ?? 0), 2),
                'out' => round((float) ($bucket['out'] ?? 0), 2),
            ];
        }

        foreach ($managementByCategory as $categoryId => $bucket) {
            if (($bucket['in'] ?? 0) <= 0 && ($bucket['out'] ?? 0) <= 0) {
                continue;
            }

            $category = $categories->get($categoryId);

            $rows[] = [
                'source' => 'management',
                'source_label' => 'Управленческий',
                'category_id' => $categoryId,
                'name' => $category?->name ?? 'Без статьи',
                'in' => round((float) ($bucket['in'] ?? 0), 2),
                'out' => round((float) ($bucket['out'] ?? 0), 2),
            ];
        }

        usort($rows, fn (array $left, array $right): int => [$left['source'], $left['name']] <=> [$right['source'], $right['name']]);

        $operational = $this->netBucket($operationalTotals);
        $management = $this->netBucket($managementTotals);

        return [
            'operational' => $operational,
            'management' => $management,
            'combined' => [
                'in' => round($operational['in'] + $management['in'], 2),
                'out' => round($operational['out'] + $management['out'], 2),
                'net' => round($operational['net'] + $management['net'], 2),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{in: float, out: float}  $totals
     * @param  array<int, array{in: float, out: float}>  $byCategory
     */
    private function addBucket(array &$totals, array &$byCategory, string $direction, float $amount, ?int $categoryId): void
    {
        if ($amount <= 0) {
            return;
        }

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
     * @param  array{in: float, out: float}  $totals
     * @return array{in: float, out: float, net: float}
     */
    private function netBucket(array $totals): array
    {
        $in = round((float) ($totals['in'] ?? 0), 2);
        $out = round((float) ($totals['out'] ?? 0), 2);

        return [
            'in' => $in,
            'out' => $out,
            'net' => round($in - $out, 2),
        ];
    }
}
