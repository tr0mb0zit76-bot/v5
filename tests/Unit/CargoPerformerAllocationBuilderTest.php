<?php

namespace Tests\Unit;

use App\Support\CargoPerformerAllocationBuilder;
use Tests\TestCase;

class CargoPerformerAllocationBuilderTest extends TestCase
{
    public function test_builds_default_duplicate_for_single_carrier_per_leg(): void
    {
        $performers = [
            ['stage' => 'leg_1', 'carrier_mode' => 'single', 'split_carriers' => []],
            ['stage' => 'leg_2', 'carrier_mode' => 'single', 'split_carriers' => []],
        ];

        $cargoItem = [
            'name' => 'Конвейерная лента',
            'package_count' => 5,
            'weight_value' => 2500,
            'weight_unit' => 'kg',
        ];

        $result = CargoPerformerAllocationBuilder::resolveForCargoItem($cargoItem, $performers);

        $this->assertCount(2, $result);
        $this->assertSame('leg_1', $result[0]['stage']);
        $this->assertSame(5.0, $result[0]['package_count']);
        $this->assertSame('leg_2', $result[1]['stage']);
        $this->assertSame(5.0, $result[1]['package_count']);
    }

    public function test_skips_auto_fill_for_split_leg_and_keeps_user_allocations(): void
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

        $cargoItem = [
            'name' => 'Конвейерная лента',
            'package_count' => 5,
            'weight_value' => 2500,
            'weight_unit' => 'kg',
            'performer_allocations' => [
                ['stage' => 'leg_1', 'carrier_slot' => null, 'package_count' => 5],
                ['stage' => 'leg_2', 'carrier_slot' => 1, 'package_count' => 3],
                ['stage' => 'leg_2', 'carrier_slot' => 2, 'package_count' => 2],
            ],
        ];

        $result = CargoPerformerAllocationBuilder::resolveForCargoItem($cargoItem, $performers);

        $this->assertCount(3, $result);
        $this->assertSame(3.0, $result[1]['package_count']);
        $this->assertSame(2.0, $result[2]['package_count']);
    }

    public function test_split_leg_without_user_input_only_fills_non_split_legs(): void
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

        $cargoItem = [
            'name' => 'Конвейерная лента',
            'package_count' => 5,
            'weight_value' => 2500,
            'weight_unit' => 'kg',
        ];

        $result = CargoPerformerAllocationBuilder::resolveForCargoItem($cargoItem, $performers);

        $this->assertCount(1, $result);
        $this->assertSame('leg_1', $result[0]['stage']);
        $this->assertSame(5.0, $result[0]['package_count']);
    }

    public function test_returns_empty_for_single_performer_without_split(): void
    {
        $performers = [
            ['stage' => 'leg_1', 'carrier_mode' => 'single', 'split_carriers' => []],
        ];

        $cargoItem = [
            'name' => 'Конвейерная лента',
            'package_count' => 5,
        ];

        $this->assertSame([], CargoPerformerAllocationBuilder::resolveForCargoItem($cargoItem, $performers));
        $this->assertFalse(CargoPerformerAllocationBuilder::needsPerformerAllocation($performers));
    }
}
