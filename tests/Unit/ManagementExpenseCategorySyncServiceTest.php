<?php

namespace Tests\Unit;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementExpenseCategorySyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'budget_opex_articles',
            'management_expense_categories',
        ]);

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('flow', 8)->default('out');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('include_in_budget')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_opex_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('cost_type', 32)->default('fixed_monthly');
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('management_expense_category_id')->nullable();
            $table->timestamps();
        });
    }

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
