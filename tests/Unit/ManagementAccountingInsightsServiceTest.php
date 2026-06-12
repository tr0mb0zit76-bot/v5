<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingInsightsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingInsightsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_statement_lines',
            'management_statement_imports',
            'management_expense_categories',
            'budget_opex_articles',
            'payment_schedule_payment_events',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('can_management_accounting')->default(false);
            $table->timestamps();
        });

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

        Schema::create('management_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_account_id')->default(1);
            $table->string('format', 32)->default('xlsx');
            $table->string('file_name');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedBigInteger('imported_by');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedInteger('lines_allocated')->default(0);
            $table->decimal('total_in', 14, 2)->default(0);
            $table->decimal('total_out', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->nullable();
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
            $table->timestamps();
        });

        Schema::create('budget_opex_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('cost_type', 32);
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->unsignedBigInteger('management_expense_category_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_insights_returns_cfo_payload_for_management_accounting_user(): void
    {
        $user = User::query()->create([
            'name' => 'Analyst',
            'email' => 'analyst@example.com',
            'password' => bcrypt('secret'),
            'can_management_accounting' => true,
        ]);

        ManagementExpenseCategory::query()->create([
            'code' => 'operational_customer_in',
            'name' => 'Оплата от заказчика',
            'kind' => 'operational_in',
            'flow' => 'in',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $expense = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банк',
            'kind' => 'overhead',
            'flow' => 'out',
            'is_active' => true,
            'include_in_budget' => true,
            'sort_order' => 20,
        ]);

        \DB::table('payment_schedule_payment_events')->insert([
            'order_id' => 1,
            'party' => 'customer',
            'amount' => 200000,
            'payment_date' => '2026-06-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => null,
            'bank_account_id' => 1,
            'line_hash' => 'out-1',
            'operation_date' => '2026-06-12',
            'direction' => 'out',
            'amount' => 50000,
            'status' => 'allocated',
            'allocation_category_id' => $expense->id,
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => 1,
            'file_name' => 'june.xlsx',
            'imported_by' => $user->id,
            'lines_count' => 2,
            'lines_allocated' => 1,
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => 1,
            'line_hash' => 'pending-1',
            'operation_date' => '2026-06-15',
            'direction' => 'out',
            'amount' => 12000,
            'status' => 'pending',
        ]);

        $result = app(ManagementAccountingInsightsService::class)->insights($user, 'month', '2026-06-01');

        $this->assertTrue($result['available']);
        $this->assertSame('month', $result['period']['type']);
        $this->assertNotEmpty($result['executive_headline']);
        $this->assertSame(200000.0, $result['kpis']['revenue']);
        $this->assertGreaterThan(0, $result['reconciliation_health']['pending_lines']);
        $this->assertNotEmpty($result['recommendations']);
        $this->assertNotEmpty($result['risk_flags']);
        $this->assertNotEmpty($result['expense_mix']);
        $this->assertSame('Банк', $result['expense_mix'][0]['name']);
    }

    public function test_insights_denied_without_management_accounting_access(): void
    {
        $user = User::query()->create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
            'can_management_accounting' => false,
        ]);

        $result = app(ManagementAccountingInsightsService::class)->insights($user);

        $this->assertFalse($result['available']);
    }
}
