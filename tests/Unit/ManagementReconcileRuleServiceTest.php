<?php

namespace Tests\Unit;

use App\Models\ManagementReconcileRule;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementReconcileRuleService;
use Tests\TestCase;

class ManagementReconcileRuleServiceTest extends TestCase
{
    public function test_matches_keyword_with_higher_priority_first(): void
    {
        $category = $this->createManagementExpenseCategory([
            'name' => 'Комиссии',
        ]);

        ManagementReconcileRule::query()->create([
            'keyword' => 'комиссия',
            'direction' => 'out',
            'allocation_type' => 'category',
            'category_id' => $category->id,
            'priority' => 50,
            'is_active' => true,
        ]);

        ManagementReconcileRule::query()->create([
            'keyword' => 'комиссия сбера',
            'direction' => 'out',
            'allocation_type' => 'category',
            'category_id' => $category->id,
            'priority' => 200,
            'is_active' => true,
        ]);

        $match = app(ManagementReconcileRuleService::class)->matchDescription(
            'Списание комиссия сбера за обслуживание',
            'out',
        );

        $this->assertNotNull($match);
        $this->assertSame('category', $match['match_type']);
        $this->assertSame($category->id, $match['suggested_category_id']);
        $this->assertSame(95, $match['match_confidence']);
    }

    public function test_remember_creates_active_rule(): void
    {
        $user = User::query()->create([
            'name' => 'Accountant',
            'email' => 'acc@example.com',
            'password' => bcrypt('secret'),
        ]);

        $category = $this->createManagementExpenseCategory([
            'name' => 'АТИ',
            'sort_order' => 2,
        ]);

        $rule = app(ManagementReconcileRuleService::class)->remember($user, [
            'keyword' => 'автотрансинфо',
            'direction' => 'out',
            'allocation_type' => 'category',
            'category_id' => $category->id,
            'notes' => 'Подписка АТИ',
        ]);

        $this->assertSame('автотрансинфо', $rule->keyword);
        $this->assertTrue($rule->is_active);
        $this->assertSame($user->id, $rule->created_by);
    }
}
