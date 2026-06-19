<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\ManagementAccounting\ManagementAccountingAnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingAnalyticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'payment_schedule_payment_events',
            'management_statement_lines',
            'management_expense_categories',
            'budget_plan_snapshot_lines',
            'budget_plan_snapshots',
            'budget_scenarios',
            'budget_opex_articles',
        ]);

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('flow', 8)->default('out');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('include_in_budget')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_account_id')->default(1);
            $table->string('line_hash', 64);
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('allocation_category_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->string('party', 16);
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
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
    }

    public function test_builds_monthly_actuals_and_plan_totals(): void
    {
        $category = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банковские комиссии',
            'kind' => 'overhead',
            'is_system' => true,
            'is_active' => true,
            'include_in_budget' => true,
            'sort_order' => 10,
        ]);

        ManagementStatementLine::query()->create([
            'bank_account_id' => 1,
            'line_hash' => 'hash-out',
            'operation_date' => '2026-06-05',
            'direction' => 'out',
            'amount' => 1500,
            'status' => 'allocated',
            'allocation_category_id' => $category->id,
        ]);

        ManagementStatementLine::query()->create([
            'bank_account_id' => 1,
            'line_hash' => 'hash-in',
            'operation_date' => '2026-06-10',
            'direction' => 'in',
            'amount' => 50000,
            'status' => 'allocated',
            'allocation_category_id' => $category->id,
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
        $category = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банковские комиссии',
            'kind' => 'overhead',
            'is_system' => true,
            'is_active' => true,
            'include_in_budget' => true,
            'sort_order' => 10,
        ]);

        \DB::table('budget_scenarios')->insert([
            'name' => 'Основной',
            'inputs' => json_encode(['horizon_months' => 12]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshotId = \DB::table('budget_plan_snapshots')->insertGetId([
            'scenario_id' => 1,
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
        $customerCategory = ManagementExpenseCategory::query()->create([
            'code' => 'operational_customer_in',
            'name' => 'Оплата от заказчика',
            'kind' => 'operational_in',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => 5,
            'party' => 'customer',
            'amount' => 620000,
            'payment_date' => '2026-03-15',
            'transaction_reference' => null,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => 5,
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
        ManagementExpenseCategory::query()->create([
            'code' => 'operational_customer_in',
            'name' => 'Оплата от заказчика',
            'kind' => 'operational_in',
            'flow' => 'in',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => 1,
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
        ManagementExpenseCategory::query()->create([
            'code' => 'operational_customer_in',
            'name' => 'Оплата от заказчика',
            'kind' => 'operational_in',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => 5,
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
