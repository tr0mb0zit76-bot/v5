<?php

namespace Tests\Unit;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use Tests\TestCase;

class ManagementExpenseCategorySyncServiceTest extends TestCase
{
    public function test_sync_ensures_system_categories_and_hierarchy_without_budget_clone(): void
    {
        BudgetOpexArticle::query()->create([
            'name' => 'Офис',
            'cost_type' => 'fixed_monthly',
            'amount_monthly' => 100000,
            'sort_order' => 10,
        ]);

        $managerArticle = BudgetOpexArticle::query()->create([
            'name' => 'Оклады менеджеров',
            'cost_type' => 'fixed_monthly',
            'amount_monthly' => 75000,
            'sort_order' => 20,
        ]);

        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $this->assertTrue(ManagementExpenseCategory::query()->where('code', 'bank_fees')->exists());
        $this->assertTrue(ManagementExpenseCategory::query()->where('code', 'group_taxes')->exists());
        $this->assertFalse(ManagementExpenseCategory::query()->where('code', 'like', 'budget_opex_%')->exists());
        $this->assertTrue(
            (bool) ManagementExpenseCategory::query()->where('code', 'bank_fees')->value('include_in_budget'),
        );
        $this->assertTrue(ManagementExpenseCategory::query()->where('code', 'payroll_managers')->exists());
        $this->assertTrue(ManagementExpenseCategory::query()->where('code', 'payroll_office')->exists());
        $this->assertFalse(
            (bool) ManagementExpenseCategory::query()->where('code', 'payroll_other')->value('is_active'),
        );

        $payrollGroupId = ManagementExpenseCategory::query()->where('code', 'group_payroll')->value('id');
        $this->assertSame(
            ['payroll_managers', 'payroll_office'],
            ManagementExpenseCategory::query()
                ->where('parent_id', $payrollGroupId)
                ->orderBy('sort_order')
                ->pluck('code')
                ->all(),
        );

        $managerCategoryId = ManagementExpenseCategory::query()->where('code', 'payroll_managers')->value('id');
        $this->assertSame($managerCategoryId, $managerArticle->fresh()->management_expense_category_id);
    }
}
