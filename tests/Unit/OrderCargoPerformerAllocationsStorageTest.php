<?php

namespace Tests\Unit;

use App\Support\CargoPerformerAllocationNormalizer;
use Tests\TestCase;

class OrderCargoPerformerAllocationsStorageTest extends TestCase
{
    public function test_normalize_for_storage_normalizes_stage_and_skips_empty_rows(): void
    {
        $result = CargoPerformerAllocationNormalizer::normalizeForStorage([
            [
                'stage' => 'Плечо 1',
                'carrier_slot' => null,
                'package_count' => 5,
                'weight_value' => null,
            ],
            [
                'stage' => 'leg_2',
                'carrier_slot' => 1,
                'package_count' => 3,
                'weight_value' => 7500,
            ],
            [
                'stage' => 'leg_2',
                'carrier_slot' => 2,
                'package_count' => null,
                'weight_value' => null,
            ],
        ]);

        $this->assertSame([
            [
                'stage' => 'leg_1',
                'carrier_slot' => null,
                'package_count' => 5.0,
                'weight_value' => null,
            ],
            [
                'stage' => 'leg_2',
                'carrier_slot' => 1,
                'package_count' => 3.0,
                'weight_value' => 7500.0,
            ],
        ], $result);
    }
}
