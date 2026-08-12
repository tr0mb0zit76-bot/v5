<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Support\OrderDocumentEpdFulfillment;
use Tests\TestCase;

class OrderDocumentEpdFulfillmentTest extends TestCase
{
    public function test_is_epd_slot_kind(): void
    {
        $this->assertTrue(OrderDocumentEpdFulfillment::isEpdSlotKind('etrn'));
        $this->assertTrue(OrderDocumentEpdFulfillment::isEpdSlotKind('expedition_receipt'));
        $this->assertFalse(OrderDocumentEpdFulfillment::isEpdSlotKind('waybill'));
    }

    public function test_fulfilled_by_sent_file(): void
    {
        $rule = [
            'slot_kind' => 'etrn',
            'party' => 'carrier',
            'accepted_types' => ['etrn'],
        ];

        $document = new OrderDocument([
            'type' => 'etrn',
            'status' => 'sent',
            'metadata' => ['party' => 'carrier'],
        ]);

        $this->assertTrue(OrderDocumentEpdFulfillment::isRuleFulfilled($rule, [$document], []));
    }

    public function test_fulfilled_by_edo_acknowledgement(): void
    {
        $rule = [
            'slot_kind' => 'expedition_receipt',
            'party' => 'customer',
            'accepted_types' => ['expedition_receipt'],
        ];

        $ack = new OrderDocumentEdoAcknowledgement([
            'party' => 'customer',
            'document_type' => 'expedition_receipt',
            'received_via_edo' => true,
            'document_number' => 'ЭР-1',
        ]);

        $this->assertTrue(OrderDocumentEpdFulfillment::isRuleFulfilled($rule, [], [$ack]));
    }

    public function test_not_fulfilled_without_number(): void
    {
        $rule = [
            'slot_kind' => 'etrn',
            'party' => 'carrier',
            'accepted_types' => ['etrn'],
        ];

        $ack = new OrderDocumentEdoAcknowledgement([
            'party' => 'carrier',
            'document_type' => 'etrn',
            'received_via_edo' => true,
            'document_number' => '',
        ]);

        $this->assertFalse(OrderDocumentEpdFulfillment::isRuleFulfilled($rule, [], [$ack]));
    }
}
