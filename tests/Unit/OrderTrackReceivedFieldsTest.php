<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderTrackReceivedFields;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackReceivedFieldsTest extends TestCase
{
    public function test_resolve_for_payment_basis_prefers_package_over_legacy(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_customer_request')) {
            $this->markTestSkipped('Колонки request/closing недоступны.');
        }

        $order = Order::factory()->create([
            'track_received_date_customer' => '2026-08-01',
            'track_received_date_customer_request' => '2026-08-05',
            'track_received_date_customer_closing' => '2026-08-12',
        ]);

        $this->assertSame(
            '2026-08-05',
            OrderTrackReceivedFields::resolveForPaymentBasis($order, 'customer', 'ottn')?->toDateString(),
        );
        $this->assertSame(
            '2026-08-12',
            OrderTrackReceivedFields::resolveForPaymentBasis($order, 'customer', 'fttn_receipt')?->toDateString(),
        );
    }

    public function test_legacy_sync_uses_latest_package_date(): void
    {
        if (! Schema::hasColumn('orders', 'track_received_date_customer_request')) {
            $this->markTestSkipped('Колонки request/closing недоступны.');
        }

        $order = Order::factory()->create([
            'track_received_date_customer_request' => '2026-08-05',
            'track_received_date_customer_closing' => '2026-08-12',
        ]);

        $sync = OrderTrackReceivedFields::legacySyncAttributes($order, OrderTrackReceivedFields::CUSTOMER_REQUEST);

        $this->assertSame(
            '2026-08-12',
            optional($sync[OrderTrackReceivedFields::CUSTOMER_LEGACY])->toDateString()
                ?? (string) $sync[OrderTrackReceivedFields::CUSTOMER_LEGACY],
        );
    }

    public function test_field_for_slot_kind_maps_packages(): void
    {
        $this->assertSame(
            OrderTrackReceivedFields::CUSTOMER_REQUEST,
            OrderTrackReceivedFields::fieldForSlotKind('customer', 'customer_request'),
        );
        $this->assertSame(
            OrderTrackReceivedFields::CARRIER_CLOSING,
            OrderTrackReceivedFields::fieldForSlotKind('carrier', 'carrier_closing'),
        );
        $this->assertNull(OrderTrackReceivedFields::fieldForSlotKind('customer', 'transport'));
    }
}
