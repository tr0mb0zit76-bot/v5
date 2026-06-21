<?php

namespace Tests\Unit;

use App\Support\OrderDocumentRequirementSlotBuilder;
use App\Support\OwnFleetCatalog;
use PHPUnit\Framework\TestCase;

class OwnFleetIntegrationTest extends TestCase
{
    public function test_own_fleet_execution_mode_constant(): void
    {
        $this->assertTrue(OwnFleetCatalog::isOwnFleetExecutionMode(OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET));
        $this->assertFalse(OwnFleetCatalog::isOwnFleetExecutionMode('external'));
    }

    public function test_document_rules_skip_own_fleet_carrier_slots(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'carrier_mode' => 'single',
            'contractor_id' => 42,
            'contractor_name' => 'Собственный парк',
            'execution_mode' => OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET,
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request');

        $carrierRules = array_values(array_filter(
            $rules,
            fn (array $rule): bool => in_array($rule['slot_kind'], ['carrier_request', 'carrier_closing'], true),
        ));

        $this->assertSame([], $carrierRules);

        $customerRules = array_values(array_filter(
            $rules,
            fn (array $rule): bool => str_starts_with((string) $rule['slot_kind'], 'customer_'),
        ));

        $this->assertNotEmpty($customerRules);
    }

    public function test_mixed_performers_keep_external_carrier_documents(): void
    {
        $performers = [
            [
                'stage' => 'leg_1',
                'carrier_mode' => 'split',
                'split_carriers' => [
                    [
                        'slot' => 1,
                        'contractor_id' => 10,
                        'contractor_name' => 'Внешний перевозчик',
                        'execution_mode' => null,
                    ],
                    [
                        'slot' => 2,
                        'contractor_id' => 42,
                        'contractor_name' => 'Собственный парк',
                        'execution_mode' => OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET,
                    ],
                ],
            ],
        ];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request');
        $carrierRules = array_values(array_filter(
            $rules,
            fn (array $rule): bool => $rule['slot_kind'] === 'carrier_request',
        ));

        $this->assertCount(1, $carrierRules);
        $this->assertSame(10, $carrierRules[0]['contractor_id']);
    }
}
