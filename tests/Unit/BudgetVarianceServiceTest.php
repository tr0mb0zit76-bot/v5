<?php

namespace Tests\Unit;

use App\Models\BudgetPlanSnapshot;
use App\Models\BudgetPlanSnapshotLine;
use App\Models\BudgetScenario;
use App\Models\ManagementExpenseCategory;
use App\Models\User;
use App\Services\Budgeting\BudgetVarianceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BudgetVarianceServiceTest extends TestCase
{
    public function test_compare_returns_variance_by_category(): void
    {
        $user = User::factory()->create(['name' => 'CFO']);
        $category = ManagementExpenseCategory::query()
            ->where('code', 'bank_fees')
            ->firstOrFail();

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

        $this->createManagementStatementLine([
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
