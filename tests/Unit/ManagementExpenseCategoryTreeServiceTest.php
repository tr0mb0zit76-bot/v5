<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementExpenseCategoryTreeService;
use Tests\TestCase;

class ManagementExpenseCategoryTreeServiceTest extends TestCase
{
    public function test_creates_nested_category_under_group(): void
    {
        $group = $this->createManagementExpenseCategory([
            'code' => 'group_payroll_test',
            'name' => 'ФОТ',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'sort_order' => 10,
        ]);

        $created = app(ManagementExpenseCategoryTreeService::class)->create('Бухгалтерия', $group->id);

        $category = ManagementExpenseCategory::query()->findOrFail($created['id']);

        $this->assertSame($group->id, $category->parent_id);
        $this->assertSame('Бухгалтерия', $category->name);
    }

    public function test_builds_tree_for_ui(): void
    {
        $root = $this->createManagementExpenseCategory([
            'code' => 'group_expense_test',
            'name' => 'Расходы тест',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'sort_order' => 10,
        ]);

        $this->createManagementExpenseCategory([
            'parent_id' => $root->id,
            'code' => 'custom_test',
            'name' => 'Аренда',
            'kind' => 'overhead',
            'flow' => 'out',
            'is_system' => false,
            'sort_order' => 20,
        ]);

        $tree = app(ManagementExpenseCategoryTreeService::class)->treeForUi();

        $expenseRoot = collect($tree)->firstWhere('code', 'group_expense_test');
        $this->assertNotNull($expenseRoot);
        $this->assertCount(1, $expenseRoot['children']);
        $this->assertSame('Аренда', $expenseRoot['children'][0]['name']);
    }
}
