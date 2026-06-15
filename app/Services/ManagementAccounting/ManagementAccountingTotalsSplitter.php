<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Support\ManagementCostCategoryCodes;
use Illuminate\Support\Collection;

final class ManagementAccountingTotalsSplitter
{
    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @param  array<int, array{in: float, out: float}>  $byCategory
     * @return array{
     *     actual_out_cost: float,
     *     actual_out_budget: float,
     *     actual_out_other: float,
     *     gross_margin: float,
     *     gross_margin_percent: float|null,
     *     budget_variance: float,
     *     budget_execution_percent: float|null
     * }
     */
    public function split(
        Collection $categories,
        array $byCategory,
        float $actualIn,
        float $actualOut,
        float $planOut,
    ): array {
        $flags = $this->categoryFlags($categories);

        $actualOutCost = 0.0;
        $actualOutBudget = 0.0;
        $actualOutOther = 0.0;

        foreach ($byCategory as $categoryId => $bucket) {
            $out = (float) ($bucket['out'] ?? 0.0);

            if ($out <= 0) {
                continue;
            }

            $categoryFlag = $flags[(int) $categoryId] ?? ['is_cost' => false, 'is_budget' => false];

            if ($categoryFlag['is_cost']) {
                $actualOutCost += $out;

                continue;
            }

            if ($categoryFlag['is_budget']) {
                $actualOutBudget += $out;

                continue;
            }

            $actualOutOther += $out;
        }

        $actualOutCost = round($actualOutCost, 2);
        $actualOutBudget = round($actualOutBudget, 2);
        $actualOutOther = round($actualOutOther, 2);

        $grossMargin = round($actualIn - $actualOutCost, 2);
        $budgetVariance = round($actualOutBudget - $planOut, 2);

        return [
            'actual_out_cost' => $actualOutCost,
            'actual_out_budget' => $actualOutBudget,
            'actual_out_other' => $actualOutOther,
            'gross_margin' => $grossMargin,
            'gross_margin_percent' => $actualIn > 0
                ? round(($grossMargin / $actualIn) * 100, 1)
                : null,
            'budget_variance' => $budgetVariance,
            'budget_execution_percent' => $planOut > 0
                ? round(($actualOutBudget / $planOut) * 100, 1)
                : null,
        ];
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @return array<int, array{is_cost: bool, is_budget: bool}>
     */
    private function categoryFlags(Collection $categories): array
    {
        /** @var array<int, ManagementExpenseCategory> $byId */
        $byId = $categories->keyBy('id')->all();
        $flags = [];

        foreach ($categories as $category) {
            $flags[(int) $category->id] = [
                'is_cost' => $this->isCostCategory($category, $byId),
                'is_budget' => $this->isBudgetCategory($category, $byId),
            ];
        }

        return $flags;
    }

    /**
     * @param  array<int, ManagementExpenseCategory>  $byId
     */
    private function isCostCategory(ManagementExpenseCategory $category, array $byId): bool
    {
        if (in_array($category->code, ManagementCostCategoryCodes::costLeafCodes(), true)) {
            return true;
        }

        if (str_starts_with((string) $category->kind, 'operational_out')) {
            return true;
        }

        return $this->hasAncestorCode($category, $byId, 'group_cost');
    }

    /**
     * @param  array<int, ManagementExpenseCategory>  $byId
     */
    private function isBudgetCategory(ManagementExpenseCategory $category, array $byId): bool
    {
        if ((bool) $category->include_in_budget) {
            return true;
        }

        if (in_array($category->kind, ['payroll_other', 'payroll_accrued', 'payroll_paid', 'overhead'], true)) {
            return true;
        }

        foreach (['group_payroll', 'group_overhead', 'group_taxes'] as $groupCode) {
            if ($this->hasAncestorCode($category, $byId, $groupCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, ManagementExpenseCategory>  $byId
     */
    private function hasAncestorCode(ManagementExpenseCategory $category, array $byId, string $code): bool
    {
        if ($category->code === $code) {
            return true;
        }

        $current = $category;

        while ($current->parent_id !== null) {
            $parent = $byId[(int) $current->parent_id] ?? null;

            if ($parent === null) {
                break;
            }

            if ($parent->code === $code) {
                return true;
            }

            $current = $parent;
        }

        return false;
    }
}
