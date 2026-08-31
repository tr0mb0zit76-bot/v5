<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderTrackReceivedFields;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackReceivedFieldsOriginalsTest extends TestCase
{
    public function test_originals_start_date_requires_both_request_and_closing(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_customer_request')) {
            $this->markTestSkipped('Колонки request/closing недоступны.');
        }

        $order = Order::factory()->create([
            'track_received_date_customer_request' => '2026-08-05',
            'track_received_date_customer_closing' => null,
        ]);

        $this->assertNull(OrderTrackReceivedFields::resolveOriginalsPaymentStartDate($order, 'customer'));
        $this->assertNull(OrderTrackReceivedFields::resolveForPaymentBasis($order, 'customer', 'ottn'));
        $this->assertNull(OrderTrackReceivedFields::resolveForPaymentBasis($order, 'customer', 'fttn_receipt'));

        $order->forceFill(['track_received_date_customer_closing' => '2026-08-12'])->saveQuietly();

        $this->assertSame(
            '2026-08-12',
            OrderTrackReceivedFields::resolveOriginalsPaymentStartDate($order->fresh(), 'customer')?->toDateString(),
        );
    }

    public function test_originals_bases_share_the_same_start_date(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_carrier_request')) {
            $this->markTestSkipped('Колонки request/closing недоступны.');
        }

        $order = Order::factory()->create([
            'track_received_date_carrier_request' => '2026-08-01',
            'track_received_date_carrier_closing' => '2026-08-09',
        ]);

        $this->assertSame(
            '2026-08-09',
            OrderTrackReceivedFields::resolveForPaymentBasis($order, 'carrier', 'ottn')?->toDateString(),
        );
        $this->assertSame(
            '2026-08-09',
            OrderTrackReceivedFields::resolveForPaymentBasis($order, 'carrier', 'fttn_receipt')?->toDateString(),
        );
    }
}
