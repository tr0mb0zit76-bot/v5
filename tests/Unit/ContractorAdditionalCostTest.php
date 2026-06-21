<?php

namespace Tests\Unit;

use App\Services\OrderWizardService;
use App\Support\ContractorCostRowClassification;
use ReflectionMethod;
use Tests\TestCase;

class ContractorAdditionalCostTest extends TestCase
{
    public function test_is_additional_detects_flag_and_stage_prefix(): void
    {
        $this->assertTrue(ContractorCostRowClassification::isAdditional([
            'is_additional' => true,
            'stage' => 'leg_1',
        ]));
        $this->assertTrue(ContractorCostRowClassification::isAdditional([
            'stage' => 'additional_2',
        ]));
        $this->assertFalse(ContractorCostRowClassification::isAdditional([
            'stage' => 'leg_1',
            'contractor_id' => 10,
        ]));
    }

    public function test_next_additional_stage_increments_index(): void
    {
        $this->assertSame('additional_1', ContractorCostRowClassification::nextAdditionalStage([]));
        $this->assertSame('additional_2', ContractorCostRowClassification::nextAdditionalStage([
            ['stage' => 'additional_1'],
        ]));
    }

    public function test_sync_contractors_costs_with_performers_preserves_additional_rows(): void
    {
        $service = app(OrderWizardService::class);
        $method = new ReflectionMethod(OrderWizardService::class, 'syncContractorsCostsWithPerformers');
        $method->setAccessible(true);

        $costs = [
            [
                'stage' => 'leg_1',
                'carrier_slot' => null,
                'contractor_id' => 100,
                'amount' => 50000,
                'payment_schedule' => ['installments' => [['amount' => 50000]]],
            ],
            [
                'stage' => 'additional_1',
                'carrier_slot' => null,
                'contractor_id' => 200,
                'amount' => 3000,
                'is_additional' => true,
                'incurred_date' => '2026-05-01',
                'payment_schedule' => ['installments' => [['amount' => 3000]]],
            ],
        ];

        $performers = [
            [
                'stage' => 'leg_1',
                'carrier_mode' => 'single',
                'contractor_id' => 101,
            ],
        ];

        /** @var list<array<string, mixed>> $result */
        $result = $method->invoke($service, $costs, $performers);

        $this->assertSame(101, $result[0]['contractor_id']);
        $this->assertSame(50000.0, (float) $result[0]['amount']);
        $this->assertSame(200, $result[1]['contractor_id']);
        $this->assertSame(3000.0, (float) $result[1]['amount']);
        $this->assertTrue($result[1]['is_additional']);
        $this->assertSame('2026-05-01', $result[1]['incurred_date']);
    }
}
