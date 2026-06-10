<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementReconcileRule;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementReconcileRuleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementReconcileRuleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_reconcile_rules',
            'management_expense_categories',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('management_reconcile_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable();
            $table->string('keyword', 128);
            $table->string('direction', 8)->nullable();
            $table->string('allocation_type', 16);
            $table->foreignId('category_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('order_number', 32)->nullable();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedInteger('times_applied')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_matches_keyword_with_higher_priority_first(): void
    {
        $category = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Комиссии',
            'kind' => 'overhead',
            'is_active' => true,
            'sort_order' => 1,
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

        $category = ManagementExpenseCategory::query()->create([
            'code' => 'ati',
            'name' => 'АТИ',
            'kind' => 'overhead',
            'is_active' => true,
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
