<?php

namespace Tests\Unit;

use App\Support\OrderCargoItemsPayloadNormalizer;
use Tests\TestCase;

class OrderCargoItemsPayloadNormalizerTest extends TestCase
{
    public function test_normalizes_cargo_item_from_root_and_ati_payload_allocations(): void
    {
        $performers = [
            ['stage' => 'leg_1', 'carrier_mode' => 'single', 'split_carriers' => []],
            [
                'stage' => 'leg_2',
                'carrier_mode' => 'split',
                'split_carriers' => [
                    ['slot' => 1, 'contractor_id' => 12],
                    ['slot' => 2, 'contractor_id' => 16],
                ],
            ],
        ];

        $item = [
            'name' => 'Конвейерная лента',
            'cargo_type' => 'general',
            'package_count' => 5,
            'weight_value' => 2500,
            'weight_unit' => 'kg',
            'ati_cargo_payload' => [],
            'performer_allocations' => [
                ['stage' => 'leg_1', 'carrier_slot' => null, 'package_count' => 5],
                ['stage' => 'leg_2', 'carrier_slot' => 1, 'package_count' => 3],
                ['stage' => 'leg_2', 'carrier_slot' => 2, 'package_count' => 2],
            ],
        ];

        $normalized = OrderCargoItemsPayloadNormalizer::normalizeCargoItem($item, $performers);

        $this->assertCount(3, $normalized['performer_allocations']);
        $this->assertSame(3.0, (float) $normalized['performer_allocations'][1]['package_count']);
        $this->assertSame(3, count($normalized['ati_cargo_payload']['performer_allocations']));
    }

    public function test_builds_default_allocations_when_only_customer_row_present(): void
    {
        $performers = [
            ['stage' => 'leg_1', 'carrier_mode' => 'single', 'split_carriers' => []],
            ['stage' => 'leg_2', 'carrier_mode' => 'single', 'split_carriers' => []],
        ];

        $normalized = OrderCargoItemsPayloadNormalizer::normalizeCargoItem([
            'name' => 'Конвейерная лента',
            'package_count' => 5,
            'weight_value' => 2500,
            'weight_unit' => 'kg',
        ], $performers);

        $this->assertCount(2, $normalized['performer_allocations']);
        $this->assertSame(5.0, (float) $normalized['performer_allocations'][0]['package_count']);
    }
}
