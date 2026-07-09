<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\OrderDocumentEdoAcknowledgement;
use App\Support\OrderDocumentClosingFulfillment;
use PHPUnit\Framework\TestCase;

class OrderDocumentClosingFulfillmentTest extends TestCase
{
    public function test_closing_rule_is_fulfilled_by_upd_only(): void
    {
        $rule = [
            'party' => 'customer',
            'slot_kind' => 'customer_closing',
            'slot_key' => 'customer-all',
            'contractor_id' => null,
        ];

        $documents = [[
            'type' => 'upd',
            'party' => 'customer',
            'status' => 'signed',
        ]];

        $this->assertTrue(OrderDocumentClosingFulfillment::isRuleFulfilled($rule, $documents, []));
    }

    public function test_closing_rule_is_fulfilled_by_invoice_factura_and_act_pair(): void
    {
        $rule = [
            'party' => 'customer',
            'slot_kind' => 'customer_closing',
            'slot_key' => 'customer-all',
            'contractor_id' => null,
        ];

        $documents = [
            ['type' => 'act', 'party' => 'customer', 'status' => 'signed'],
            ['type' => 'invoice_factura', 'party' => 'customer', 'status' => 'signed'],
        ];

        $this->assertTrue(OrderDocumentClosingFulfillment::isRuleFulfilled($rule, $documents, []));
    }

    public function test_closing_rule_is_not_fulfilled_by_act_without_invoice_factura(): void
    {
        $rule = [
            'party' => 'customer',
            'slot_kind' => 'customer_closing',
            'slot_key' => 'customer-all',
            'contractor_id' => null,
        ];

        $documents = [
            ['type' => 'act', 'party' => 'customer', 'status' => 'signed'],
        ];

        $this->assertFalse(OrderDocumentClosingFulfillment::isRuleFulfilled($rule, $documents, []));
    }

    public function test_closing_rule_is_fulfilled_by_edo_acknowledgement(): void
    {
        $rule = [
            'party' => 'customer',
            'slot_kind' => 'customer_closing',
            'slot_key' => 'customer-all',
            'contractor_id' => null,
        ];

        $acknowledgement = new OrderDocumentEdoAcknowledgement([
            'party' => 'customer',
            'document_type' => 'upd',
            'slot_key' => 'customer-all',
            'contractor_id' => 0,
            'received_via_edo' => true,
            'document_number' => 'UPD-100',
        ]);

        $this->assertTrue(OrderDocumentClosingFulfillment::isRuleFulfilled($rule, [], [$acknowledgement]));
    }
}
