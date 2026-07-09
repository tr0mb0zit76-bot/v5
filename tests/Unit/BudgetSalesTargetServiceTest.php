<?php

namespace Tests\Unit;

use App\Models\BudgetSalesTarget;
use App\Models\BudgetScenario;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Budgeting\BudgetSalesPerformanceService;
use App\Services\Budgeting\BudgetSalesScenarioService;
use App\Services\Budgeting\BudgetSalesTargetService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetSalesTargetServiceTest extends TestCase
{
    public function test_ensure_child_sales_scenario_is_created_once(): void
    {
        $company = BudgetScenario::query()->create([
            'name' => 'Основной',
            'plan_type' => BudgetScenario::PLAN_TYPE_COMPANY,
            'inputs' => [],
        ]);

        $service = app(BudgetSalesScenarioService::class);
        $first = $service->ensureForCompanyScenario($company);
        $second = $service->ensureForCompanyScenario($company);

        $this->assertSame(BudgetScenario::PLAN_TYPE_SALES_PAYROLL, $first->plan_type);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($company->id, $first->parent_scenario_id);
    }

    public function test_upsert_and_build_payload_with_order_and_lead_actuals(): void
    {
        $company = BudgetScenario::query()->create([
            'name' => 'Основной',
            'plan_type' => BudgetScenario::PLAN_TYPE_COMPANY,
            'inputs' => ['horizon_months' => 12],
        ]);

        $seller = $this->makeSellerUser('Продавец плана');
        $periodMonth = CarbonImmutable::parse('2026-07-01');

        app(BudgetSalesTargetService::class)->upsert($company, $periodMonth, [
            ['user_id' => $seller->id, 'metric' => BudgetSalesTarget::METRIC_REVENUE, 'planned_value' => 1_000_000],
            ['user_id' => $seller->id, 'metric' => BudgetSalesTarget::METRIC_MARGIN, 'planned_value' => 200_000],
            ['user_id' => $seller->id, 'metric' => BudgetSalesTarget::METRIC_LEADS, 'planned_value' => 5],
            ['user_id' => $seller->id, 'metric' => BudgetSalesTarget::METRIC_ORDERS, 'planned_value' => 3],
        ]);

        $this->seedClosedOrderForSeller($seller, $periodMonth, 500_000, 120_000);
        $this->seedWonLeadForSeller($seller, $periodMonth);

        $payload = app(BudgetSalesTargetService::class)->buildPayload($company, $periodMonth->toDateString(), 12);
        $sellerRow = collect($payload['sellers'])->firstWhere('id', $seller->id);

        $this->assertNotNull($sellerRow);
        $this->assertSame(1_000_000.0, $sellerRow['metrics']['revenue']['planned']);
        $this->assertSame(500_000.0, $sellerRow['metrics']['revenue']['actual']);
        $this->assertSame(-500_000.0, $sellerRow['metrics']['revenue']['variance']);
        $this->assertSame(120_000.0, $sellerRow['metrics']['margin']['actual']);
        $this->assertSame(1.0, $sellerRow['metrics']['leads']['actual']);
        $this->assertSame(1.0, $sellerRow['metrics']['orders']['actual']);
    }

    public function test_performance_service_returns_zero_for_users_without_activity(): void
    {
        $seller = $this->makeSellerUser('Пустой продавец');
        $periodMonth = CarbonImmutable::parse('2026-08-01');

        $actuals = app(BudgetSalesPerformanceService::class)->actualsForMonth($periodMonth, [$seller->id]);

        $this->assertSame([
            BudgetSalesTarget::METRIC_REVENUE => 0.0,
            BudgetSalesTarget::METRIC_MARGIN => 0.0,
            BudgetSalesTarget::METRIC_LEADS => 0.0,
            BudgetSalesTarget::METRIC_ORDERS => 0.0,
        ], $actuals[$seller->id]);
    }

    private function makeSellerUser(string $name): User
    {
        $role = Role::query()->create([
            'name' => 'seller_'.uniqid(),
            'display_name' => 'Seller',
            'permissions' => [],
            'visibility_areas' => ['leads', 'orders'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'name' => $name,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function seedClosedOrderForSeller(User $seller, CarbonImmutable $periodMonth, float $revenue, float $margin): void
    {
        $attributes = [
            'order_number' => 'ORD-'.uniqid(),
            'company_code' => 'ORD',
            'order_date' => $periodMonth->toDateString(),
            'status' => 'closed',
            'is_active' => true,
            'customer_rate' => $revenue,
            'delta' => $margin,
            'status_updated_at' => $periodMonth->addDays(10)->toDateTimeString(),
        ];

        if (Schema::hasColumn('orders', 'manager_id')) {
            $attributes['manager_id'] = $seller->id;
        }

        if (Schema::hasColumn('orders', 'order_owner_id')) {
            $attributes['order_owner_id'] = $seller->id;
        }

        Order::query()->create($attributes);
    }

    private function seedWonLeadForSeller(User $seller, CarbonImmutable $periodMonth): void
    {
        Lead::query()->create([
            'number' => 'L-'.uniqid(),
            'status' => 'won',
            'responsible_id' => $seller->id,
            'title' => 'Тестовый лид',
            'updated_at' => $periodMonth->addDays(5),
        ]);
    }
}
