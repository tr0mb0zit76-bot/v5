<?php

namespace Tests\Unit;

use App\Models\ManagementStatementLine;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Services\Finance\FinanceOverviewService;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingOperationalAllocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'payment_schedule_payment_events',
            'management_statement_lines',
            'management_expense_categories',
            'payment_schedules',
            'orders',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
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
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
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
            'code' => 'operational_carrier_out',
            'name' => 'Перевозчики',
            'direction' => 'out',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_partial_allocation_keeps_open_row_with_correct_remaining(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Бухгалтер',
            'email' => 'acct@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);
        $carrierId = DB::table('contractors')->insertGetId(['name' => 'ООО Камион']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-KAM',
            'carrier_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 400000,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'overdue',
            'planned_date' => '2026-05-01',
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = ManagementStatementLine::query()->create([
            'line_hash' => hash('sha256', 'kamion'),
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 100000,
            'description' => 'Оплата ООО Камион',
            'status' => 'pending',
            'source' => 'manual',
        ]);

        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);

        app(ManagementAccountingAllocationService::class)->allocateLine($line, [
            'allocation_type' => 'operational',
            'payment_schedule_id' => $schedule->id,
            'amount' => 100000,
        ], $user);

        $schedule->refresh();

        $this->assertSame(100000.0, (float) $schedule->paid_amount);
        $this->assertSame(300000.0, (float) $schedule->remaining_amount);
        $this->assertContains($schedule->status, ['pending', 'overdue']);

        $journalRow = app(FinanceOverviewService::class)
            ->cashFlowJournal(null, 'admin', 'all')
            ->firstWhere('id', $scheduleId);

        $this->assertNotNull($journalRow);
        $this->assertSame(300000.0, $journalRow['remaining_amount']);
        $this->assertSame('ООО Камион', $journalRow['counterparty_name']);
    }

    public function test_full_prepayment_allocation_hides_row_from_cash_flow_journal(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Бухгалтер',
            'email' => 'acct@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);
        $customerId = DB::table('contractors')->insertGetId(['name' => 'ООО Дайтона моторс']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'АС-2606-0001',
            'customer_id' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 617231,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'pending',
            'planned_date' => '2026-06-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = ManagementStatementLine::query()->create([
            'line_hash' => hash('sha256', 'daytona'),
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 617231,
            'description' => 'Оплата от Дайтона',
            'status' => 'pending',
            'source' => 'manual',
        ]);

        app(ManagementAccountingAllocationService::class)->allocateLine($line, [
            'allocation_type' => 'operational',
            'payment_schedule_id' => $scheduleId,
            'amount' => 617231,
        ], $user);

        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);
        $this->assertSame('paid', $schedule->status);
        $this->assertSame(0.0, (float) $schedule->remaining_amount);

        $journal = app(FinanceOverviewService::class)->cashFlowJournal(null, 'admin', 'all');
        $this->assertNull($journal->firstWhere('id', $scheduleId));
    }

    public function test_sync_command_repairs_row_hidden_after_buggy_allocation(): void
    {
        $carrierId = DB::table('contractors')->insertGetId(['name' => 'ООО Камион']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-REPAIR',
            'carrier_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 400000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
            'status' => 'pending',
            'planned_date' => '2026-05-01',
            'actual_date' => '2026-06-02',
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'contractor_id' => $carrierId,
            'payment_schedule_id' => $scheduleId,
            'party' => 'carrier',
            'amount' => 100000,
            'payment_date' => '2026-06-02',
            'transaction_reference' => 'mgmt:99',
        ]);

        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);
        app(PaymentScheduleSettlementSyncService::class)->syncRootSchedule($schedule);

        $schedule->refresh();
        $this->assertSame(300000.0, (float) $schedule->remaining_amount);

        $journalRow = app(FinanceOverviewService::class)
            ->cashFlowJournal(null, 'admin', 'all')
            ->firstWhere('id', $scheduleId);

        $this->assertNotNull($journalRow);
        $this->assertSame(300000.0, $journalRow['remaining_amount']);
    }

    public function test_reallocate_operational_payment_moves_settlement_to_another_schedule(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Бухгалтер',
            'email' => 'acct@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);
        $carrierId = DB::table('contractors')->insertGetId(['name' => 'ООО Камион']);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-REALLOC',
            'carrier_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prepaymentId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'prepayment',
            'amount' => 400000,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'pending',
            'planned_date' => '2026-05-01',
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $finalId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 400000,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'overdue',
            'planned_date' => '2026-06-07',
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = ManagementStatementLine::query()->create([
            'line_hash' => hash('sha256', 'kamion-realloc'),
            'operation_date' => '2026-06-11',
            'direction' => 'out',
            'amount' => 400000,
            'description' => 'Оплата ООО Камион',
            'status' => 'pending',
            'source' => 'manual',
        ]);

        $service = app(ManagementAccountingAllocationService::class);

        $service->allocateLine($line, [
            'allocation_type' => 'operational',
            'payment_schedule_id' => $prepaymentId,
            'amount' => 400000,
        ], $user);

        $service->allocateLine($line->fresh(), [
            'allocation_type' => 'operational',
            'payment_schedule_id' => $finalId,
            'amount' => 400000,
        ], $user);

        $prepayment = PaymentSchedule::query()->findOrFail($prepaymentId);
        $final = PaymentSchedule::query()->findOrFail($finalId);

        $this->assertSame(0.0, (float) $prepayment->paid_amount);
        $this->assertContains($prepayment->status, ['pending', 'overdue']);
        $this->assertNull($prepayment->actual_date);
        $this->assertSame('paid', $final->status);
        $this->assertSame(400000.0, (float) $final->paid_amount);
        $this->assertSame(0.0, (float) $final->remaining_amount);

        $journal = app(FinanceOverviewService::class)->cashFlowJournal(null, 'admin', 'all');
        $this->assertNotNull($journal->firstWhere('id', $prepaymentId));
        $this->assertNull($journal->firstWhere('id', $finalId));
    }
}
