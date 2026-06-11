<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Support\ManagementExpenseCategoryCatalog;
use Illuminate\Support\Facades\Schema;

class ManagementExpenseCategoryHierarchyService
{
    public function __construct(
        private readonly ManagementExpenseCategorySyncService $syncService,
    ) {}

    public function ensureDefaultHierarchy(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        $this->syncService->ensureSystemCategories();

        $incomeRoot = $this->upsertGroup('group_income', 'Доходы', 'in', 5);
        $expenseRoot = $this->upsertGroup('group_expense', 'Расходы', 'out', 10);
        $costGroup = $this->upsertGroup('group_cost', 'Себестоимость', 'out', 20, $expenseRoot->id);
        $payrollGroup = $this->upsertGroup('group_payroll', 'ФОТ', 'out', 30, $expenseRoot->id);
        $overheadGroup = $this->upsertGroup('group_overhead', 'Накладные расходы', 'out', 40, $expenseRoot->id);

        $this->attachUnder($incomeRoot->id, [
            'operational_customer_in',
            'cash_other_in',
        ]);

        $this->attachUnder($costGroup->id, [
            'operational_carrier_out',
            'cost_own_fleet',
        ]);

        $this->attachUnder($payrollGroup->id, [
            'payroll_accrued_sales',
            'payroll_paid_sales',
            'payroll_other',
        ]);

        $this->attachUnder($overheadGroup->id, [
            'bank_fees',
            'services_other',
        ]);

        $this->attachUnder($expenseRoot->id, [
            'cash_other_out',
            'unclassified',
        ]);

        $this->syncService->syncFromBudgetOpexArticles();
        $this->attachBudgetArticlesToOverhead($overheadGroup->id);
    }

    private function upsertGroup(
        string $code,
        string $name,
        string $flow,
        int $sortOrder,
        ?int $parentId = null,
    ): ManagementExpenseCategory {
        return ManagementExpenseCategory::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'kind' => 'group',
                'flow' => $flow,
                'parent_id' => $parentId,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }

    /**
     * @param  list<string>  $codes
     */
    private function attachUnder(int $parentId, array $codes): void
    {
        foreach ($codes as $index => $code) {
            $category = ManagementExpenseCategory::query()->where('code', $code)->first();

            if ($category === null) {
                continue;
            }

            $flow = in_array($category->kind, ['operational_in', 'cash'], true) && str_ends_with($code, '_in')
                ? 'in'
                : (str_contains($category->kind, 'in') ? 'in' : 'out');

            if ($code === 'cash_other_in') {
                $flow = 'in';
            }

            $category->forceFill([
                'parent_id' => $parentId,
                'flow' => $flow,
                'sort_order' => 10 + ($index * 10),
            ])->save();
        }
    }

    private function attachBudgetArticlesToOverhead(int $overheadGroupId): void
    {
        ManagementExpenseCategory::query()
            ->where('code', 'like', 'budget_opex_%')
            ->where(function ($query) use ($overheadGroupId): void {
                $query->whereNull('parent_id')
                    ->orWhere('parent_id', '!=', $overheadGroupId);
            })
            ->update(['parent_id' => $overheadGroupId, 'flow' => 'out']);
    }

    /**
     * @return list<string>
     */
    public static function systemCategoryCodes(): array
    {
        return array_column(ManagementExpenseCategoryCatalog::systemCategories(), 'code');
    }
}
