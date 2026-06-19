<?php

namespace Tests\Unit;

use App\Models\BudgetOpexArticle;
use App\Models\BudgetPlanSnapshot;
use App\Models\BudgetScenario;
use App\Models\ManagementExpenseCategory;
use App\Models\User;
use App\Services\Budgeting\BudgetPlanSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetPlanSnapshotServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'budget_plan_snapshot_lines',
            'budget_plan_snapshots',
            'budget_opex_articles',
            'budget_scenarios',
            'management_expense_categories',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32)->nullable();
            $table->boolean('include_in_budget')->default(false);
            $table->timestamps();
        });

        Schema::create('budget_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('plan_type', 32)->default('company');
            $table->unsignedBigInteger('parent_scenario_id')->nullable();
            $table->json('inputs');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_opex_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('cost_type', 32);
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->decimal('percent_of_margin', 8, 2)->nullable();
            $table->unsignedTinyInteger('ramp_months')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('management_expense_category_id')->nullable();
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
            $table->text('notes')->nullable();
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
    }

    public function test_freeze_creates_monthly_snapshot_lines(): void
    {
        $user = User::query()->create(['name' => 'CFO', 'email' => 'cfo@test']);
        $category = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банк',
            'include_in_budget' => true,
        ]);

        $scenario = BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => ['horizon_months' => 12],
        ]);

        BudgetOpexArticle::query()->create([
            'name' => 'Банк',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_monthly' => 50000,
            'management_expense_category_id' => $category->id,
            'sort_order' => 10,
        ]);

        $result = app(BudgetPlanSnapshotService::class)->freeze(
            $scenario,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
            'Q1 2026',
            $user,
        );

        $this->assertSame(3, $result['lines_count']);
        $this->assertSame(150000.0, app(BudgetPlanSnapshotService::class)->totalPlannedOutflow(
            BudgetPlanSnapshot::query()->findOrFail($result['id']),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
        ));
    }

    public function test_resolve_snapshot_for_period_returns_latest_approved(): void
    {
        $user = User::query()->create(['name' => 'CFO', 'email' => 'cfo@test']);
        $scenario = BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => ['horizon_months' => 12],
        ]);

        BudgetPlanSnapshot::query()->create([
            'scenario_id' => $scenario->id,
            'period_label' => 'Старый',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'approved_at' => '2026-01-10 10:00:00',
            'approved_by_user_id' => $user->id,
        ]);

        $latest = BudgetPlanSnapshot::query()->create([
            'scenario_id' => $scenario->id,
            'period_label' => 'Новый',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'approved_at' => '2026-02-10 10:00:00',
            'approved_by_user_id' => $user->id,
        ]);

        $resolved = app(BudgetPlanSnapshotService::class)->resolveSnapshotForPeriod(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertNotNull($resolved);
        $this->assertSame($latest->id, $resolved->id);
    }
}
