<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OrderDocumentClosingFulfillment;
use App\Support\OrderDocumentDirection;
use PHPUnit\Framework\TestCase;

class OrderDocumentDirectionTest extends TestCase
{
    public function test_normalize_defaults_missing_to_incoming(): void
    {
        $this->assertSame(OrderDocumentDirection::INCOMING, OrderDocumentDirection::normalize(null));
        $this->assertSame(OrderDocumentDirection::INCOMING, OrderDocumentDirection::normalize('bogus'));
        $this->assertSame(OrderDocumentDirection::OUTGOING, OrderDocumentDirection::normalize('outgoing'));
    }

    public function test_from_document_reads_array_shapes(): void
    {
        $this->assertTrue(OrderDocumentDirection::isOutgoing([
            'direction' => 'outgoing',
            'party' => 'customer',
        ]));
        $this->assertTrue(OrderDocumentDirection::isOutgoing([
            'metadata' => ['direction' => 'outgoing'],
        ]));
        $this->assertFalse(OrderDocumentDirection::isOutgoing([
            'party' => 'customer',
        ]));
    }

    public function test_outgoing_document_does_not_fulfill_closing_rule(): void
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
            'direction' => 'outgoing',
        ]];

        $this->assertFalse(OrderDocumentClosingFulfillment::isRuleFulfilled($rule, $documents, []));
    }

    public function test_labels_and_options(): void
    {
        $this->assertSame('Исходящий', OrderDocumentDirection::label('outgoing'));
        $this->assertSame('Входящий', OrderDocumentDirection::label('incoming'));
        $this->assertCount(2, OrderDocumentDirection::options());
    }
}
