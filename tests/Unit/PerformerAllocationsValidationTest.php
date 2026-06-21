<?php

namespace Tests\Unit;

use App\Http\Requests\StoreOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PerformerAllocationsValidationTest extends TestCase
{
    public function test_cargo_performer_allocations_pass_validation(): void
    {
        $rules = (new StoreOrderRequest)->rules();

        $validator = Validator::make([
            'cargo_items' => [
                [
                    'name' => 'Конвейерная лента',
                    'cargo_type' => 'general',
                    'package_count' => 5,
                    'performer_allocations' => [
                        [
                            'stage' => 'leg_1',
                            'carrier_slot' => null,
                            'package_count' => 5,
                            'weight_value' => 12500,
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
                            'package_count' => 2,
                            'weight_value' => 5000,
                        ],
                    ],
                ],
            ],
        ], [
            'cargo_items' => $rules['cargo_items'],
            'cargo_items.*.name' => $rules['cargo_items.*.name'],
            'cargo_items.*.cargo_type' => $rules['cargo_items.*.cargo_type'],
            'cargo_items.*.package_count' => $rules['cargo_items.*.package_count'],
            'cargo_items.*.performer_allocations' => $rules['cargo_items.*.performer_allocations'],
            'cargo_items.*.performer_allocations.*.stage' => $rules['cargo_items.*.performer_allocations.*.stage'],
            'cargo_items.*.performer_allocations.*.carrier_slot' => $rules['cargo_items.*.performer_allocations.*.carrier_slot'],
            'cargo_items.*.performer_allocations.*.package_count' => $rules['cargo_items.*.performer_allocations.*.package_count'],
            'cargo_items.*.performer_allocations.*.weight_value' => $rules['cargo_items.*.performer_allocations.*.weight_value'],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }
}
