<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentScheduleSettlementSyncServiceTest extends TestCase
{
    public function test_partial_payment_sync_keeps_overdue_status_after_refresh(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('payment_schedule_payment_events')) {
            $this->markTestSkipped('Таблицы графика оплат недоступны.');
        }

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-OVERDUE-PARTIAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Перевозчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'status' => 'overdue',
            'planned_date' => now()->subDays(10)->toDateString(),
            'counterparty_id' => $carrierId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'contractor_id' => $carrierId,
            'payment_schedule_id' => $scheduleId,
            'party' => 'carrier',
            'amount' => 25000,
            'payment_date' => now()->toDateString(),
        ]);

        $schedule = PaymentSchedule::query()->findOrFail($scheduleId);
        app(PaymentScheduleSettlementSyncService::class)->syncRootSchedule($schedule);

        $schedule->refresh();
        $this->assertSame('overdue', $schedule->status);
        $this->assertSame(25000.0, (float) $schedule->paid_amount);
        $this->assertSame(75000.0, (float) $schedule->remaining_amount);
    }
}
