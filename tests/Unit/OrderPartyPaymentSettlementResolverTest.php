<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Support\OrderPartyPaymentSettlementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderPartyPaymentSettlementResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_is_paid_when_latest_root_schedules_are_settled(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('payment_schedules table is unavailable.');
        }

        $order = Order::factory()->create();

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'overdue',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 1000,
            'paid_amount' => 1000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 500,
            'paid_amount' => 500,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $this->assertTrue(OrderPartyPaymentSettlementResolver::isPartyFullyPaid($order->fresh(), 'customer'));
    }

    public function test_customer_is_not_paid_when_latest_root_schedule_is_open(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('payment_schedules table is unavailable.');
        }

        $order = Order::factory()->create();

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'status' => 'overdue',
        ]);

        $this->assertFalse(OrderPartyPaymentSettlementResolver::isPartyFullyPaid($order->fresh(), 'customer'));
    }
}
