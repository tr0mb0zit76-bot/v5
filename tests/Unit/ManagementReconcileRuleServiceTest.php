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

    public function test_extract_auto_keyword_from_org_name(): void
    {
        $keyword = app(ManagementReconcileRuleService::class)->extractAutoKeyword(
            'Оплата по счету ООО "Яндекс" за рекламу 15000.00'
        );

        $this->assertNotNull($keyword);
        $this->assertStringContainsString('яндекс', mb_strtolower($keyword));
    }

    public function test_learn_from_manual_allocation_upserts_by_keyword(): void
    {
        $user = User::query()->create([
            'name' => 'Accountant 2',
            'email' => 'acc2@example.com',
            'password' => bcrypt('secret'),
        ]);

        $category = $this->createManagementExpenseCategory([
            'name' => 'Реклама',
            'sort_order' => 3,
        ]);

        $line = $this->createManagementStatementLine([
            'direction' => 'out',
            'amount' => 15000,
            'description' => 'Оплата ООО Яндекс за рекламные услуги',
            'status' => 'allocated',
            'match_type' => 'category',
            'allocation_category_id' => $category->id,
        ]);

        $service = app(ManagementReconcileRuleService::class);
        $first = $service->learnFromManualAllocation($user, $line);
        $this->assertNotNull($first);
        $this->assertSame($category->id, $first->category_id);

        $secondCategory = $this->createManagementExpenseCategory([
            'name' => 'Маркетинг',
            'sort_order' => 4,
        ]);
        $line->allocation_category_id = $secondCategory->id;
        $line->save();

        $second = $service->learnFromManualAllocation($user, $line->fresh());
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($secondCategory->id, $second->category_id);
        $this->assertSame(2, (int) $second->times_applied);
        $this->assertSame(1, ManagementReconcileRule::query()->where('keyword', $first->keyword)->count());
    }

    public function test_learn_skips_when_crm_token_present(): void
    {
        $user = User::query()->create([
            'name' => 'Accountant 3',
            'email' => 'acc3@example.com',
            'password' => bcrypt('secret'),
        ]);

        $category = $this->createManagementExpenseCategory([
            'name' => 'Прочее',
            'sort_order' => 5,
        ]);

        $line = $this->createManagementStatementLine([
            'direction' => 'out',
            'amount' => 1000,
            'description' => 'Оплата по заказу АС-2608-0001 CRM:АС-2608-0001:P1',
            'status' => 'allocated',
            'match_type' => 'category',
            'allocation_category_id' => $category->id,
        ]);

        $learned = app(ManagementReconcileRuleService::class)->learnFromManualAllocation($user, $line);
        $this->assertNull($learned);
    }
}
