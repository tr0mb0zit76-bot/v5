<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Support\DocumentRegistryOrderAttentionResolver;
use Tests\TestCase;

class DocumentRegistryOrderAttentionResolverTest extends TestCase
{
    public function test_flags_missing_documents_when_unloading_is_set_and_checklist_is_incomplete(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_payment_form' => 'bank_transfer',
        ]);

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => '2026-06-10',
        ]);

        $payload = app(DocumentRegistryOrderAttentionResolver::class)
            ->payloadForOrder($order->fresh(['documents', 'legs.routePoints']));

        $this->assertTrue($payload['missing_documents_after_unloading']);
        $this->assertNotEmpty($payload['missing_document_labels']);
    }

    public function test_does_not_flag_when_unloading_is_not_set(): void
    {
        $order = Order::factory()->create([
            'customer_payment_form' => 'bank_transfer',
        ]);

        $payload = app(DocumentRegistryOrderAttentionResolver::class)
            ->payloadForOrder($order->fresh(['documents', 'legs.routePoints']));

        $this->assertFalse($payload['missing_documents_after_unloading']);
        $this->assertSame([], $payload['missing_document_labels']);
    }

    public function test_does_not_flag_when_required_documents_are_complete(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);
        $carrier = Contractor::query()->create([
            'name' => 'ООО Перевоз',
            'type' => 'carrier',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'customer_payment_form' => 'bank_transfer',
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrier->id,
                    'contractor_name' => $carrier->name,
                ],
            ],
        ]);

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => '2026-06-10',
        ]);

        foreach ([
            ['type' => 'request', 'party' => 'customer'],
            ['type' => 'request', 'party' => 'carrier'],
            ['type' => 'upd', 'party' => 'customer'],
            ['type' => 'upd', 'party' => 'carrier'],
            ['type' => 'waybill', 'party' => 'internal'],
        ] as $document) {
            OrderDocument::query()->create([
                'order_id' => $order->id,
                'type' => $document['type'],
                'status' => 'signed',
                'original_name' => $document['type'].'.pdf',
                'file_path' => 'orders/'.$order->id.'/'.$document['type'].'.pdf',
                'metadata' => ['party' => $document['party'], 'flow' => 'uploaded'],
                'entity_type' => 'order',
                'entity_id' => $order->id,
            ]);
        }

        $payload = app(DocumentRegistryOrderAttentionResolver::class)
            ->payloadForOrder($order->fresh(['documents', 'legs.routePoints']));

        $this->assertFalse($payload['missing_documents_after_unloading']);
        $this->assertSame([], $payload['missing_document_labels']);
    }
}
