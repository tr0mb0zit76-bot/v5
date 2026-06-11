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

    public function test_sync_creates_system_and_budget_categories(): void
    {
        $opex = BudgetOpexArticle::query()->create([
            'name' => 'Офис',
            'cost_type' => 'fixed_monthly',
            'amount_monthly' => 100000,
            'sort_order' => 10,
        ]);

        app(ManagementExpenseCategorySyncService::class)->syncAll();

        $this->assertGreaterThanOrEqual(10, ManagementExpenseCategory::query()->count());
        $this->assertTrue(ManagementExpenseCategory::query()->where('code', 'bank_fees')->exists());

        $budgetCategory = ManagementExpenseCategory::query()
            ->where('code', 'budget_opex_'.$opex->id)
            ->first();

        $this->assertNotNull($budgetCategory);
        $this->assertSame('Офис', $budgetCategory->name);
        $this->assertSame($budgetCategory->id, $opex->fresh()->management_expense_category_id);
    }
}
