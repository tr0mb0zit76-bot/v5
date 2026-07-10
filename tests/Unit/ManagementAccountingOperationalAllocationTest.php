<?php

namespace Tests\Unit;

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Services\Finance\FinanceOverviewService;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagementAccountingOperationalAllocationTest extends TestCase
{
    public function test_partial_allocation_keeps_open_row_with_correct_remaining(): void
    {
        $user = User::factory()->create();
        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Камион',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-KAM',
            'carrier_id' => $carrierId,
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 400000,
            'paid_amount' => 0,
            'remaining_amount' => 400000,
            'status' => 'overdue',
            'planned_date' => '2026-05-01',
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createManagementStatementLine([
            'line_hash' => hash('sha256', 'kamion'),
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 100000,
            'description' => 'Оплата ООО Камион',
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
            ->cashFlowJournal(null)
            ->firstWhere('id', $scheduleId);

        $this->assertNotNull($journalRow);
        $this->assertSame(300000.0, $journalRow['remaining_amount']);
        $this->assertSame('ООО Камион', $journalRow['counterparty_name']);
    }

    public function test_full_prepayment_allocation_hides_row_from_cash_flow_journal(): void
    {
        $user = User::factory()->create();
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Дайтона моторс',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'АС-2606-0001',
            'customer_id' => $customerId,
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 617231,
            'paid_amount' => 0,
            'remaining_amount' => 617231,
            'status' => 'pending',
            'planned_date' => '2026-06-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createManagementStatementLine([
            'line_hash' => hash('sha256', 'daytona'),
            'operation_date' => '2026-06-11',
            'direction' => 'in',
            'amount' => 617231,
            'description' => 'Оплата от Дайтона',
        ]);

        app(ManagementAccountingAllocationService::class)->allocateLine($line, [
            'allocation_type' => 'operational',
            'payment_schedule_id' => $scheduleId,
            'amount' => 617231,
        ], $user);

        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);
        $this->assertSame('paid', $schedule->status);
        $this->assertSame(0.0, (float) $schedule->remaining_amount);

        $journal = app(FinanceOverviewService::class)->cashFlowJournal(null);
        $this->assertNull($journal->firstWhere('id', $scheduleId));
    }

    public function test_sync_command_repairs_row_hidden_after_buggy_allocation(): void
    {
        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Камион',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-REPAIR',
            'carrier_id' => $carrierId,
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
            ->cashFlowJournal(null)
            ->firstWhere('id', $scheduleId);

        $this->assertNotNull($journalRow);
        $this->assertSame(300000.0, $journalRow['remaining_amount']);
    }

    public function test_reallocate_operational_payment_moves_settlement_to_another_schedule(): void
    {
        $user = User::factory()->create();
        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Камион',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-REALLOC',
            'carrier_id' => $carrierId,
        ]);

        $prepaymentId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'prepayment',
            'amount' => 400000,
            'paid_amount' => 0,
            'remaining_amount' => 400000,
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
            'remaining_amount' => 400000,
            'status' => 'overdue',
            'planned_date' => '2026-06-07',
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createManagementStatementLine([
            'line_hash' => hash('sha256', 'kamion-realloc'),
            'operation_date' => '2026-06-11',
            'direction' => 'out',
            'amount' => 400000,
            'description' => 'Оплата ООО Камион',
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

        $journal = app(FinanceOverviewService::class)->cashFlowJournal(null);
        $this->assertNotNull($journal->firstWhere('id', $prepaymentId));
        $this->assertNull($journal->firstWhere('id', $finalId));
    }
}
