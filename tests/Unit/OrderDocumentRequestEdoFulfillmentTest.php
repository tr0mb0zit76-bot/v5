<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Support\ContractorExpectsEdo;
use App\Support\OrderDocumentRequestEdoFulfillment;
use PHPUnit\Framework\TestCase;

class OrderDocumentRequestEdoFulfillmentTest extends TestCase
{
    public function test_is_request_slot_kind(): void
    {
        $this->assertTrue(OrderDocumentRequestEdoFulfillment::isRequestSlotKind('customer_request'));
        $this->assertTrue(OrderDocumentRequestEdoFulfillment::isRequestSlotKind('carrier_request'));
        $this->assertTrue(OrderDocumentRequestEdoFulfillment::isRequestSlotKind('contractor_request'));
        $this->assertFalse(OrderDocumentRequestEdoFulfillment::isRequestSlotKind('customer_closing'));
    }

    public function test_fulfilled_by_sent_request_file(): void
    {
        $rule = [
            'party' => 'customer',
            'slot_kind' => 'customer_request',
            'slot_key' => 'customer-all',
            'contractor_id' => null,
        ];

        $document = new OrderDocument([
            'type' => 'request',
            'status' => 'sent',
            'metadata' => [
                'party' => 'customer',
                'requirement_slot_key' => 'customer-all',
            ],
        ]);

        $this->assertTrue(OrderDocumentRequestEdoFulfillment::isRuleFulfilled($rule, [$document], []));
    }

    public function test_fulfilled_by_edo_acknowledgement(): void
    {
        $rule = [
            'party' => 'customer',
            'slot_kind' => 'customer_request',
            'slot_key' => 'customer-all',
            'contractor_id' => null,
        ];

        $acknowledgement = new OrderDocumentEdoAcknowledgement([
            'party' => 'customer',
            'document_type' => 'request',
            'slot_key' => 'customer-all',
            'contractor_id' => 0,
            'received_via_edo' => true,
            'document_number' => 'REQ-42',
        ]);

        $this->assertTrue(OrderDocumentRequestEdoFulfillment::isRuleFulfilled($rule, [], [$acknowledgement]));
    }

    public function test_not_fulfilled_without_number(): void
    {
        $rule = [
            'party' => 'carrier',
            'slot_kind' => 'carrier_request',
            'slot_key' => 'carrier-10',
            'contractor_id' => 10,
        ];

        $acknowledgement = new OrderDocumentEdoAcknowledgement([
            'party' => 'carrier',
            'document_type' => 'request',
            'slot_key' => 'carrier-10',
            'contractor_id' => 10,
            'received_via_edo' => true,
            'document_number' => '',
        ]);

        $this->assertFalse(OrderDocumentRequestEdoFulfillment::isRuleFulfilled($rule, [], [$acknowledgement]));
    }

    public function test_contractor_expects_edo_from_provider_or_number(): void
    {
        $this->assertTrue(ContractorExpectsEdo::fromFields('diadoc', null));
        $this->assertTrue(ContractorExpectsEdo::fromFields(null, '2BE…'));
        $this->assertFalse(ContractorExpectsEdo::fromFields(null, null));
        $this->assertFalse(ContractorExpectsEdo::fromFields('', '  '));
    }
}
