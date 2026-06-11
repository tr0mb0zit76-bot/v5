<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\ManagementAccounting\ManagementAccountingCategoryBreakdownService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingCategoryBreakdownServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'payment_schedule_payment_events',
            'management_statement_lines',
            'orders',
            'management_expense_categories',
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
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
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
            $table->unsignedBigInteger('allocation_order_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('party', 16);
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('transaction_reference', 100)->nullable();
            $table->timestamps();
        });
    }

    public function test_cost_breakdown_groups_carrier_payments_by_order(): void
    {
        $costGroup = ManagementExpenseCategory::query()->create([
            'code' => 'group_cost',
            'name' => 'Себестоимость',
            'kind' => 'group',
            'flow' => 'out',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $carrierCategory = ManagementExpenseCategory::query()->create([
            'parent_id' => $costGroup->id,
            'code' => 'operational_carrier_out',
            'name' => 'Привлечённый транспорт',
            'kind' => 'operational_out_hired',
            'flow' => 'out',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        DB::table('orders')->insert([
            'id' => 105,
            'order_number' => '105',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = 105;

        ManagementStatementLine::query()->create([
            'bank_account_id' => 1,
            'line_hash' => 'line-1',
            'operation_date' => '2026-06-05',
            'direction' => 'out',
            'amount' => 120000,
            'status' => 'allocated',
            'allocation_category_id' => $carrierCategory->id,
            'allocation_order_id' => $orderId,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'party' => 'carrier',
            'amount' => 30000,
            'payment_date' => '2026-06-08',
            'transaction_reference' => null,
        ]);

        $categories = ManagementExpenseCategory::query()->get();
        $start = CarbonImmutable::parse('2026-06-01');
        $end = CarbonImmutable::parse('2026-06-30');

        $result = app(ManagementAccountingCategoryBreakdownService::class)
            ->forCategory($costGroup, $categories, $start, $end);

        $this->assertSame('order', $result['label']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Заказ 105', $result['items'][0]['name']);
        $this->assertSame(150000.0, $result['items'][0]['actual_out']);
    }
}
