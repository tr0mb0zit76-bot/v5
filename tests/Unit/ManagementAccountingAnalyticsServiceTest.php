<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
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
            'management_statement_lines',
            'management_expense_categories',
            'budget_opex_articles',
        ]);

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

        Schema::create('budget_opex_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('cost_type', 32);
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->decimal('percent_of_margin', 8, 2)->nullable();
            $table->unsignedTinyInteger('ramp_months')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(ManagementAccountingAnalyticsService::class)->build('month', '2026-06-01');

        $this->assertSame('month', $result['period_type']);
        $this->assertSame(50000.0, $result['totals']['actual_in']);
        $this->assertSame(1500.0, $result['totals']['actual_out']);
        $this->assertSame(100000.0, $result['totals']['plan_out']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Банковские комиссии', $result['rows'][0]['name']);
    }
}
