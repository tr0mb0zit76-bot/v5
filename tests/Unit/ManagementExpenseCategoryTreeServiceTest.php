<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementExpenseCategoryTreeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementExpenseCategoryTreeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'budget_opex_articles',
            'management_statement_lines',
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
            $table->string('cost_type', 32);
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('management_expense_category_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_creates_nested_category_under_group(): void
    {
        $group = ManagementExpenseCategory::query()->create([
            'code' => 'group_payroll',
            'name' => 'ФОТ',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $created = app(ManagementExpenseCategoryTreeService::class)->create('Бухгалтерия', $group->id);

        $category = ManagementExpenseCategory::query()->findOrFail($created['id']);

        $this->assertSame($group->id, $category->parent_id);
        $this->assertSame('Бухгалтерия', $category->name);
    }

    public function test_builds_tree_for_ui(): void
    {
        $root = ManagementExpenseCategory::query()->create([
            'code' => 'group_expense',
            'name' => 'Расходы',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        ManagementExpenseCategory::query()->create([
            'parent_id' => $root->id,
            'code' => 'custom_test',
            'name' => 'Аренда',
            'kind' => 'overhead',
            'flow' => 'out',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $tree = app(ManagementExpenseCategoryTreeService::class)->treeForUi();

        $this->assertCount(1, $tree);
        $this->assertSame('Расходы', $tree[0]['name']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Аренда', $tree[0]['children'][0]['name']);
    }
}
