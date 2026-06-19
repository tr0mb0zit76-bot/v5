<?php

namespace Tests\Unit;

use App\Models\ManagementStatementLine;
use App\Models\ManagementStatementLineSplit;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingAllocationSplitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_statement_line_splits',
            'payment_schedule_payment_events',
            'management_statement_lines',
            'management_expense_categories',
            'payment_schedules',
            'orders',
            'contractors',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->string('party', 16);
            $table->string('type', 16);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->boolean('is_partial')->default(false);
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('direction', 8)->default('out');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('line_hash');
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('RUB');
            $table->text('description');
            $table->string('status', 16)->default('pending');
            $table->string('source', 16)->default('manual');
            $table->string('match_type', 32)->nullable();
            $table->decimal('allocation_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('allocation_category_id')->nullable();
            $table->unsignedBigInteger('allocation_order_id')->nullable();
            $table->unsignedBigInteger('allocation_payment_schedule_id')->nullable();
            $table->unsignedBigInteger('allocation_user_id')->nullable();
            $table->unsignedBigInteger('allocated_by')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('management_statement_line_splits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('management_statement_line_id');
            $table->string('allocation_type', 24);
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->string('party', 16)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        DB::table('management_expense_categories')->insert([
            'code' => 'operational_customer_in',
            'name' => 'Оплата от заказчика',
            'direction' => 'in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_split_allocation_records_two_payments_on_one_line(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Бухгалтер',
            'email' => 'acct@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);

        $customerId = DB::table('contractors')->insertGetId(['name' => 'Заказчик']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-1',
            'customer_id' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 1000000,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'pending',
            'planned_date' => '2026-06-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = ManagementStatementLine::query()->create([
            'line_hash' => hash('sha256', 'split-test'),
            'operation_date' => '2026-06-10',
            'direction' => 'in',
            'amount' => 1000000,
            'description' => 'Два платежа на одну заявку',
            'status' => 'pending',
            'source' => 'manual',
        ]);

        app(ManagementAccountingAllocationService::class)->allocateLine($line, [
            'allocation_type' => 'operational',
            'allocations' => [
                ['payment_schedule_id' => $scheduleId, 'amount' => 500000],
                ['payment_schedule_id' => $scheduleId, 'amount' => 500000],
            ],
        ], $user);

        $line->refresh();
        $this->assertSame('allocated', $line->status);
        $this->assertSame('operational_split', $line->match_type);
        $this->assertCount(2, ManagementStatementLineSplit::query()->where('management_statement_line_id', $line->id)->get());

        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);
        $this->assertSame(1000000.0, (float) $schedule->paid_amount);
        $this->assertSame('paid', $schedule->status);

        $events = PaymentSchedulePaymentEvent::query()
            ->where('transaction_reference', 'like', 'mgmt:'.$line->id.':%')
            ->count();
        $this->assertSame(2, $events);
    }
}
