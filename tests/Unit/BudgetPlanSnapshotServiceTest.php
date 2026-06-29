<?php

namespace Tests\Unit;

use App\Models\BudgetOpexArticle;
use App\Models\BudgetPlanSnapshot;
use App\Models\BudgetScenario;
use App\Models\User;
use App\Services\Budgeting\BudgetPlanSnapshotService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BudgetPlanSnapshotServiceTest extends TestCase
{
    public function test_freeze_creates_monthly_snapshot_lines(): void
    {
        $user = User::factory()->create(['name' => 'CFO', 'email' => 'cfo-freeze@test']);
        $category = $this->createManagementExpenseCategory([
            'name' => 'Банк freeze',
            'kind' => 'overhead',
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
        $user = User::factory()->create(['name' => 'CFO', 'email' => 'cfo2@test']);
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
