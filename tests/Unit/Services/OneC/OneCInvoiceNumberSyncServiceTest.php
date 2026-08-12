<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\PaymentSchedule;
use App\Services\OneC\OneCInvoiceNumberSyncService;
use Tests\TestCase;

class OneCInvoiceNumberSyncServiceTest extends TestCase
{
    public function test_syncs_invoice_number_from_realization_to_order_and_customer_schedules(): void
    {
        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2608-0777',
            'invoice_number' => null,
        ]);

        $customerSchedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
            'invoice_number' => null,
        ]);

        $carrierSchedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 80000,
            'remaining_amount' => 80000,
            'status' => 'pending',
            'invoice_number' => null,
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => 'real-with-invoice-aaaa',
            'external_number' => '0000-000081',
        ]);

        $stats = app(OneCInvoiceNumberSyncService::class)->sync();

        $this->assertSame(1, $stats['checked']);
        $this->assertSame(1, $stats['updated']);
        $this->assertSame('0000-000075', $order->fresh()->invoice_number);
        $this->assertSame('0000-000075', $customerSchedule->fresh()->invoice_number);
        $this->assertNull($carrierSchedule->fresh()->invoice_number);

        $second = app(OneCInvoiceNumberSyncService::class)->sync();
        $this->assertSame(1, $second['skipped_unchanged']);
    }

    public function test_skips_when_realization_has_no_invoice(): void
    {
        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2608-0778',
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => 'real-without-invoice-bbbb',
        ]);

        $stats = app(OneCInvoiceNumberSyncService::class)->sync();

        $this->assertSame(1, $stats['skipped_no_invoice']);
        $this->assertNull($order->fresh()->invoice_number);
    }
}
