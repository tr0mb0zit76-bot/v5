<?php

namespace Tests\Unit;

use App\Models\BudgetOpexArticle;
use App\Models\ManagementExpenseCategory;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\ManagementAccounting\ManagementAccountingAnalyticsService;
use Tests\TestCase;

class ManagementAccountingAnalyticsServiceTest extends TestCase
{
    public function test_builds_monthly_actuals_and_plan_totals(): void
    {
        $category = $this->createManagementExpenseCategory([
            'name' => 'Банковские комиссии тест',
            'kind' => 'overhead',
            'include_in_budget' => true,
            'sort_order' => 10,
        ]);

        $bankAccountId = $this->createManagementBankAccount()->id;

        $this->createManagementStatementLine([
            'bank_account_id' => $bankAccountId,
            'line_hash' => 'hash-out',
            'operation_date' => '2026-06-05',
            'direction' => 'out',
            'amount' => 1500,
            'status' => 'allocated',
            'allocation_category_id' => $category->id,
        ]);

        $this->createManagementStatementLine([
            'bank_account_id' => $bankAccountId,
            'line_hash' => 'hash-in',
            'operation_date' => '2026-06-10',
            'direction' => 'in',
            'amount' => 50000,
            'status' => 'allocated',
            'allocation_category_id' => $category->id,
        ]);

        BudgetOpexArticle::query()->create([
            'name' => 'Офис',
            'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_monthly' => 100000,
            'sort_order' => 10,
            'management_expense_category_id' => $category->id,
        ]);

        $result = app(ManagementAccountingAnalyticsService::class)->build('month', '2026-06-01');

        $this->assertSame('month', $result['period_type']);
        $this->assertSame(50000.0, $result['totals']['actual_in']);
        $this->assertSame(1500.0, $result['totals']['actual_out']);
        $this->assertSame(100000.0, $result['totals']['plan_out']);
        $this->assertSame(1500.0, $result['totals']['actual_out_budget']);
        $this->assertSame(0.0, $result['totals']['actual_out_cost']);
        $this->assertSame(97.0, $result['totals']['business_margin_percent']);
        $this->assertNotEmpty($result['rows']);
        $this->assertArrayHasKey('pivot', $result);
        $this->assertNotEmpty($result['pivot']['columns']);
        $this->assertNotEmpty($result['pivot']['time_series']);
    }

    public function test_uses_snapshot_plan_when_available(): void
    {
        $category = ManagementExpenseCategory::query()
            ->where('code', 'bank_fees')
            ->firstOrFail();

        $scenarioId = \DB::table('budget_scenarios')->insertGetId([
            'name' => 'Основной',
            'inputs' => json_encode(['horizon_months' => 12]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshotId = \DB::table('budget_plan_snapshots')->insertGetId([
            'scenario_id' => $scenarioId,
            'period_label' => 'Июнь 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-12-31',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('budget_plan_snapshot_lines')->insert([
            'snapshot_id' => $snapshotId,
            'month' => '2026-06-01',
            'category_id' => $category->id,
            'article_name' => 'Банк',
            'planned_amount' => 80000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('budget_opex_articles')->insert([
            'name' => 'Офис',
            'cost_type' => 'fixed_monthly',
            'amount_monthly' => 100000,
            'sort_order' => 10,
            'management_expense_category_id' => $category->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(ManagementAccountingAnalyticsService::class)->build('month', '2026-06-01');

        $this->assertSame('snapshot', $result['plan_source']);
        $this->assertSame(80000.0, $result['totals']['plan_out']);
        $this->assertNotEmpty($result['variance_rows']);
    }

    public function test_includes_customer_payments_from_payment_schedule_events(): void
    {
        $customerCategory = ManagementExpenseCategory::query()
            ->where('code', 'operational_customer_in')
            ->firstOrFail();

        $orderId = $this->insertOrderRow([]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 620000,
            'payment_date' => '2026-03-15',
            'transaction_reference' => null,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 620000,
            'payment_date' => '2026-03-16',
            'transaction_reference' => 'mgmt:42',
        ]);

        $result = app(ManagementAccountingAnalyticsService::class)->build('year', '2026-01-01');

        $this->assertSame(620000.0, $result['totals']['actual_in']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame($customerCategory->id, $result['rows'][0]['category_id']);
        $this->assertSame(620000.0, $result['rows'][0]['actual_in']);
    }

    public function test_pivot_includes_payment_events_when_date_is_datetime_string(): void
    {
        $orderId = $this->insertOrderRow([]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 10000,
            'payment_date' => '2026-06-03',
            'transaction_reference' => null,
        ]);

        $result = app(ManagementAccountingAnalyticsService::class)->build('month', '2026-06-01');

        $dayColumn = collect($result['pivot']['columns'])->firstWhere('key', '2026-06-03');
        $this->assertNotNull($dayColumn);

        $customerRow = collect($result['pivot']['rows'])->firstWhere('code', 'operational_customer_in');
        $this->assertNotNull($customerRow);

        $dayCell = collect($customerRow['cells'])->firstWhere('key', '2026-06-03');
        $this->assertNotNull($dayCell);
        $this->assertSame(10000.0, $dayCell['amount']);
    }

    public function test_excludes_reversed_payment_schedule_events_from_actuals(): void
    {
        $orderId = $this->insertOrderRow([]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 620000,
            'payment_date' => '2026-03-15',
            'transaction_reference' => null,
            'reversed_at' => now(),
        ]);

        $result = app(ManagementAccountingAnalyticsService::class)->build('year', '2026-01-01');

        $this->assertSame(0.0, $result['totals']['actual_in']);
    }
}
