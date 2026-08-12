<?php

declare(strict_types=1);

namespace Tests\Feature\OneC;

use App\Models\Order;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Models\OrderOneCDocument;
use App\Models\User;
use App\Services\OneC\OneCEdoStatusSyncService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OneCEdoStatusSyncServiceTest extends TestCase
{
    public function test_syncs_outgoing_edo_into_customer_upd_acknowledgement(): void
    {
        if (! Schema::hasTable('order_one_c_documents') || ! Schema::hasTable('order_document_edo_acknowledgements')) {
            $this->markTestSkipped('Таблицы 1С/ЭДО недоступны — run migrate');
        }

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2608-EDO1',
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => 'real-with-edo-aaaa',
            'external_number' => '0000-000100',
            'request_payload' => [
                'base_url' => 'https://example.test/ib',
                'publication_code' => 'autalliance',
            ],
        ]);

        $stats = app(OneCEdoStatusSyncService::class)->sync();

        $this->assertSame(1, $stats['checked']);
        $this->assertSame(1, $stats['updated']);
        $this->assertDatabaseHas('order_document_edo_acknowledgements', [
            'order_id' => $order->id,
            'party' => 'customer',
            'document_type' => 'upd',
            'document_number' => 'УПД-100',
            'received_via_edo' => true,
        ]);

        $second = app(OneCEdoStatusSyncService::class)->sync();
        $this->assertSame(1, $second['skipped_unchanged']);
    }

    public function test_skips_when_realization_has_no_edo_link(): void
    {
        if (! Schema::hasTable('order_one_c_documents') || ! Schema::hasTable('order_document_edo_acknowledgements')) {
            $this->markTestSkipped('Таблицы 1С/ЭДО недоступны — run migrate');
        }

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2608-EDO2',
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => 'real-without-edo-bbbb',
        ]);

        $stats = app(OneCEdoStatusSyncService::class)->sync();

        $this->assertSame(1, $stats['skipped_no_edo']);
        $this->assertDatabaseMissing('order_document_edo_acknowledgements', [
            'order_id' => $order->id,
        ]);
    }

    public function test_does_not_overwrite_manual_acknowledgement(): void
    {
        if (! Schema::hasTable('order_one_c_documents') || ! Schema::hasTable('order_document_edo_acknowledgements')) {
            $this->markTestSkipped('Таблицы 1С/ЭДО недоступны — run migrate');
        }

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $user = User::factory()->create();
        $order = Order::query()->create([
            'order_number' => 'АС-2608-EDO3',
        ]);

        OrderDocumentEdoAcknowledgement::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'document_type' => 'upd',
            'slot_key' => '',
            'contractor_id' => 0,
            'received_via_edo' => true,
            'document_number' => 'MANUAL-1',
            'document_date' => '2026-07-01',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => 'real-with-edo-cccc',
            'external_number' => '0000-000101',
        ]);

        $stats = app(OneCEdoStatusSyncService::class)->sync();

        $this->assertSame(1, $stats['skipped_manual']);
        $this->assertDatabaseHas('order_document_edo_acknowledgements', [
            'order_id' => $order->id,
            'document_number' => 'MANUAL-1',
            'confirmed_by' => $user->id,
        ]);
    }
}
