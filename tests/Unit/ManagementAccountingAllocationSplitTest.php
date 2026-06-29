<?php

namespace Tests\Unit;

use App\Models\ManagementStatementLineSplit;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagementAccountingAllocationSplitTest extends TestCase
{
    public function test_split_allocation_records_two_payments_on_one_line(): void
    {
        $user = User::factory()->create();

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Заказчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-1',
            'customer_id' => $customerId,
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 1000000,
            'paid_amount' => 0,
            'remaining_amount' => 1000000,
            'status' => 'pending',
            'planned_date' => '2026-06-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->createManagementStatementLine([
            'line_hash' => hash('sha256', 'split-test'),
            'operation_date' => '2026-06-10',
            'direction' => 'in',
            'amount' => 1000000,
            'description' => 'Два платежа на одну заявку',
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
