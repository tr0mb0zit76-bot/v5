<?php

namespace App\Services\ManagementAccounting;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
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
        $this->deactivateLegacyPayrollCategories();

        $incomeRoot = $this->upsertGroup('group_income', 'Доходы', 'in', 5);
        $expenseRoot = $this->upsertGroup('group_expense', 'Расходы', 'out', 10);
        $costGroup = $this->upsertGroup('group_cost', 'Себестоимость', 'out', 20, $expenseRoot->id);
        $payrollGroup = $this->upsertGroup('group_payroll', 'ФОТ', 'out', 30, $expenseRoot->id);
        $overheadGroup = $this->upsertGroup('group_overhead', 'АУР', 'out', 40, $expenseRoot->id);
        $this->upsertGroup('group_taxes', 'Налоги', 'out', 50, $expenseRoot->id);

        $this->attachUnder($incomeRoot->id, [
            'operational_customer_in',
            'cash_other_in',
        ]);

        $this->attachUnder($costGroup->id, [
            'operational_carrier_out',
            'cost_own_fleet',
        ]);

        $this->attachUnder($payrollGroup->id, [
            'payroll_managers',
            'payroll_office',
        ]);

        $this->attachUnder($overheadGroup->id, [
            'bank_fees',
            'services_other',
        ]);

        $this->attachUnder($expenseRoot->id, [
            'cash_other_out',
            'unclassified',
        ]);

        $this->migratePayrollOtherAllocations();
        $this->relinkBudgetOpexArticles();
        $this->deactivateLegacyBudgetDuplicateCategories();
    }

    private function deactivateLegacyPayrollCategories(): void
    {
        ManagementExpenseCategory::query()
            ->whereIn('code', ManagementExpenseCategoryCatalog::legacyPayrollCodes())
            ->update(['is_active' => false]);
    }

    private function migratePayrollOtherAllocations(): void
    {
        if (! Schema::hasTable('management_statement_lines')) {
            return;
        }

        $legacyId = ManagementExpenseCategory::query()->where('code', 'payroll_other')->value('id');
        $targetId = ManagementExpenseCategory::query()->where('code', 'payroll_managers')->value('id');

        if ($legacyId === null || $targetId === null) {
            return;
        }

        ManagementStatementLine::query()
            ->where('allocation_category_id', $legacyId)
            ->update(['allocation_category_id' => $targetId]);
    }

    private function relinkBudgetOpexArticles(): void
    {
        if (! Schema::hasTable('budget_opex_articles')
            || ! Schema::hasColumn('budget_opex_articles', 'management_expense_category_id')) {
            return;
        }

        $mapping = [
            'Оклады менеджеров' => 'payroll_managers',
            'Бухгалтерия' => 'payroll_office',
            'Офис' => 'services_other',
            'Постоянный ФОТ' => 'payroll_managers',
            'Постоянный фот' => 'payroll_managers',
        ];

        foreach ($mapping as $articleName => $categoryCode) {
            $categoryId = ManagementExpenseCategory::query()->where('code', $categoryCode)->value('id');

            if ($categoryId === null) {
                continue;
            }

            BudgetOpexArticle::query()
                ->where('name', $articleName)
                ->update(['management_expense_category_id' => $categoryId]);
        }
    }

    private function deactivateLegacyBudgetDuplicateCategories(): void
    {
        ManagementExpenseCategory::query()
            ->where('code', 'like', 'budget_opex_%')
            ->update(['is_active' => false]);
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

    /**
     * @return list<string>
     */
    public static function systemCategoryCodes(): array
    {
        return array_column(ManagementExpenseCategoryCatalog::systemCategories(), 'code');
    }
}
