<?php

namespace Tests\Unit;

use App\Models\BudgetPlanSnapshot;
use App\Models\BudgetPlanSnapshotLine;
use App\Models\BudgetScenario;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Services\Budgeting\BudgetVarianceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetVarianceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_statement_lines',
            'budget_plan_snapshot_lines',
            'budget_plan_snapshots',
            'budget_scenarios',
            'management_expense_categories',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('inputs');
            $table->timestamps();
        });

        Schema::create('budget_plan_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->string('period_label');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('approved_at');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_plan_snapshot_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->date('month');
            $table->unsignedBigInteger('opex_article_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('article_name');
            $table->decimal('planned_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('allocation_category_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_compare_returns_variance_by_category(): void
    {
        $user = User::query()->create(['name' => 'CFO']);
        $category = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банк',
            'kind' => 'overhead',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $scenario = BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => [],
        ]);

        $snapshot = BudgetPlanSnapshot::query()->create([
            'scenario_id' => $scenario->id,
            'period_label' => 'Июнь 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'approved_at' => now(),
            'approved_by_user_id' => $user->id,
        ]);

        BudgetPlanSnapshotLine::query()->create([
            'snapshot_id' => $snapshot->id,
            'month' => '2026-06-01',
            'category_id' => $category->id,
            'article_name' => 'Банк',
            'planned_amount' => 100000,
        ]);

        ManagementStatementLine::query()->create([
            'operation_date' => '2026-06-05',
            'direction' => 'out',
            'amount' => 120000,
            'status' => 'allocated',
            'allocation_category_id' => $category->id,
        ]);

        $categories = new Collection([$category]);
        $actualByCategory = [
            $category->id => ['in' => 0.0, 'out' => 120000.0],
        ];

        $rows = app(BudgetVarianceService::class)->compare(
            $snapshot,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            $categories,
            $actualByCategory,
        );

        $this->assertCount(1, $rows);
        $this->assertSame(100000.0, $rows[0]['planned']);
        $this->assertSame(120000.0, $rows[0]['actual']);
        $this->assertSame(20000.0, $rows[0]['variance']);
        $this->assertSame(20.0, $rows[0]['variance_percent']);
    }
}
