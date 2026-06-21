<?php

namespace Tests\Unit;

use App\Support\OrderAdditionalCostNormalizer;
use Tests\TestCase;

class OrderAdditionalCostNormalizerTest extends TestCase
{
    public function test_normalizes_additional_cost_row(): void
    {
        $row = OrderAdditionalCostNormalizer::normalizeRow([
            'contractor_id' => 5,
            'contractor_name' => 'Подрядчик А',
            'service_date' => '2026-05-10',
            'amount' => 1500,
            'currency' => 'RUB',
            'payment_form' => 'no_vat',
            'payment_schedule' => ['postpayment_days' => 5],
            'payment_terms' => '5 дней',
        ]);

        $this->assertSame(5, $row['contractor_id']);
        $this->assertSame('2026-05-10', $row['service_date']);
        $this->assertSame(1500.0, $row['amount']);
        $this->assertSame('5 дней', $row['payment_terms']);
    }

    public function test_partition_contractors_costs_extracts_legacy_additional_rows(): void
    {
        [$legCosts, $additionalCosts] = OrderAdditionalCostNormalizer::partitionContractorsCosts([
            [
                'stage' => 'leg_1',
                'contractor_id' => 10,
                'amount' => 50000,
            ],
            [
                'stage' => 'additional_1',
                'contractor_id' => 20,
                'amount' => 3000,
                'is_additional' => true,
                'incurred_date' => '2026-05-01',
            ],
        ]);

        $this->assertCount(1, $legCosts);
        $this->assertSame(10, $legCosts[0]['contractor_id']);
        $this->assertCount(1, $additionalCosts);
        $this->assertSame(20, $additionalCosts[0]['contractor_id']);
        $this->assertSame('2026-05-01', $additionalCosts[0]['service_date']);
    }
}
