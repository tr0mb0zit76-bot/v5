<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\OrderCompensationService;
use App\Services\PaymentSettlementSummaryBuilder;
use App\Support\PaymentSchedulePaymentEventRelinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentScheduleSettlementRepairTest extends TestCase
{
    public function test_resync_relinks_orphaned_ledger_events_and_preserves_partial_customer_payment(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('payment_schedule_payment_events')) {
            $this->markTestSkipped('Таблицы графика оплат недоступны.');
        }

        $manager = User::factory()->create();

        $order = $this->createOrderWithPaymentTerms([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'customer_rate' => 100000,
        ], [
            'client' => [
                'payment_schedule' => [
                    'installments' => [
                        ['percent' => 50, 'offset_days' => 0, 'offset_unit' => 'calendar_days', 'anchor' => 'order_date', 'basis' => 'fttn'],
                        ['percent' => 50, 'offset_days' => 10, 'offset_unit' => 'calendar_days', 'anchor' => 'order_date', 'basis' => 'fttn'],
                    ],
                ],
            ],
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $prepayment = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->orderBy('installment_sequence')
            ->first();

        $this->assertNotNull($prepayment);

        DB::table('payment_schedules')
            ->where('id', $prepayment->id)
            ->update([
                'paid_amount' => 50000,
                'remaining_amount' => 0,
                'status' => 'paid',
                'actual_date' => '2026-06-02',
                'updated_at' => now(),
            ]);

        DB::table('payment_schedule_payment_events')->insert([
            'order_id' => $order->id,
            'contractor_id' => $order->customer_id,
            'payment_schedule_id' => $prepayment->id,
            'party' => 'customer',
            'amount' => 50000,
            'payment_date' => '2026-06-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $orphanedCount = DB::table('payment_schedule_payment_events')
            ->where('order_id', $order->id)
            ->whereNull('payment_schedule_id')
            ->count();

        $this->assertSame(0, $orphanedCount, 'Журнал должен быть перепривязан к новым строкам графика.');

        $summary = app(PaymentSettlementSummaryBuilder::class)->forOrder($order->fresh());
        $customerLine = collect($summary['lines'])->firstWhere('party', 'customer');

        $this->assertNotNull($customerLine);
        $this->assertTrue($customerLine['has_rows']);
        $this->assertSame('partial', $customerLine['state']);
        $this->assertSame(50.0, $customerLine['percent_paid']);
    }

    public function test_repair_command_relinks_orphaned_events_for_existing_orders(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('payment_schedule_payment_events')) {
            $this->markTestSkipped('Таблицы графика оплат недоступны.');
        }

        $manager = User::factory()->create();
        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'customer_rate' => 80000,
        ]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 40000,
            'planned_date' => '2026-06-05',
            'paid_amount' => 0,
            'remaining_amount' => 40000,
            'status' => 'pending',
            'installment_sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'order_id' => $order->id,
            'contractor_id' => $order->customer_id,
            'payment_schedule_id' => null,
            'party' => 'customer',
            'amount' => 40000,
            'payment_date' => '2026-06-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $relinked = app(PaymentSchedulePaymentEventRelinker::class)->relinkOrphanedEventsForOrder((int) $order->id);
        $this->assertSame(1, $relinked);

        $this->artisan('payment-schedules:repair-settlement', ['--order' => (string) $order->id])
            ->assertSuccessful();

        $schedule = PaymentSchedule::query()->find($scheduleId);
        $this->assertNotNull($schedule);
        $this->assertEqualsWithDelta(40000.0, (float) $schedule->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $schedule->remaining_amount, 0.01);
    }
}
