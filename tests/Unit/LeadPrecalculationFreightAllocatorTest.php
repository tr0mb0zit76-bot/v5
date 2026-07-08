<?php

namespace Tests\Unit;

use App\Support\LeadPrecalculationFreightAllocator;
use PHPUnit\Framework\TestCase;

class LeadPrecalculationFreightAllocatorTest extends TestCase
{
    public function test_splits_freight_by_invoice_rub_weights(): void
    {
        $result = LeadPrecalculationFreightAllocator::apply([
            [
                'id' => 'goods_1',
                'invoice_amount' => 100,
                'currency' => 'USD',
                'exchange_rate' => 100,
                'freight_to_border' => 0,
                'freight_after_border' => 0,
            ],
            [
                'id' => 'goods_2',
                'invoice_amount' => 50,
                'currency' => 'USD',
                'exchange_rate' => 100,
                'freight_to_border' => 0,
                'freight_after_border' => 0,
            ],
        ], [
            'to_border_total' => 150_000,
            'after_border_total' => 30_000,
            'distribution_basis' => 'invoice_rub',
        ]);

        $this->assertSame(100_000, $result['allocation'][0]['freight_to_border']);
        $this->assertSame(50_000, $result['allocation'][1]['freight_to_border']);
        $this->assertSame(20_000, $result['allocation'][0]['freight_after_border']);
        $this->assertSame(10_000, $result['allocation'][1]['freight_after_border']);
        $this->assertSame(100_000, $result['goods_lines'][0]['freight_to_border']);
        $this->assertSame(50_000, $result['goods_lines'][1]['freight_to_border']);
    }

    public function test_splits_freight_equally_when_weights_missing(): void
    {
        $result = LeadPrecalculationFreightAllocator::apply([
            ['id' => 'goods_1', 'freight_to_border' => 0, 'freight_after_border' => 0],
            ['id' => 'goods_2', 'freight_to_border' => 0, 'freight_after_border' => 0],
        ], [
            'to_border_total' => 100,
            'after_border_total' => 0,
            'distribution_basis' => 'invoice_rub',
        ]);

        $this->assertSame(50, $result['allocation'][0]['freight_to_border']);
        $this->assertSame(50, $result['allocation'][1]['freight_to_border']);
    }
}
