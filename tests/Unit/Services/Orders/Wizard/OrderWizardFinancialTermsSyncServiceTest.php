<?php

namespace Tests\Unit\Services\Orders\Wizard;

use App\Services\Orders\Wizard\OrderWizardContractorsCostsNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderWizardFinancialTermsSyncServiceTest extends TestCase
{
    #[Test]
    public function merge_order_carrier_rate_applies_to_single_leg_row(): void
    {
        $normalizer = new OrderWizardContractorsCostsNormalizer;

        $result = $normalizer->mergeOrderCarrierRateIntoContractorsCosts([
            ['stage' => 'leg_1', 'amount' => null],
        ], 50000);

        $this->assertSame(50000.0, (float) $result[0]['amount']);
    }

    #[Test]
    public function merge_order_carrier_rate_distributes_across_multiple_legs(): void
    {
        $normalizer = new OrderWizardContractorsCostsNormalizer;

        $result = $normalizer->mergeOrderCarrierRateIntoContractorsCosts([
            ['stage' => 'leg_1', 'amount' => 10000, 'is_additional' => false],
            ['stage' => 'leg_2', 'amount' => 15000, 'is_additional' => false],
        ], 40000);

        $this->assertSame(25000.0, (float) $result[0]['amount']);
        $this->assertSame(15000.0, (float) $result[1]['amount']);
    }

    #[Test]
    public function merge_order_carrier_rate_skips_additional_cost_rows(): void
    {
        $normalizer = new OrderWizardContractorsCostsNormalizer;

        $result = $normalizer->mergeOrderCarrierRateIntoContractorsCosts([
            ['stage' => 'leg_1', 'amount' => null, 'is_additional' => false],
            ['stage' => 'additional_1', 'amount' => 5000, 'is_additional' => true],
        ], 30000);

        $this->assertSame(30000.0, (float) $result[0]['amount']);
        $this->assertSame(5000.0, (float) $result[1]['amount']);
    }

    #[Test]
    public function merge_order_carrier_rate_leaves_costs_unchanged_when_sum_already_matches(): void
    {
        $normalizer = new OrderWizardContractorsCostsNormalizer;
        $costs = [
            ['stage' => 'leg_1', 'amount' => 20000, 'is_additional' => false],
            ['stage' => 'leg_2', 'amount' => 10000, 'is_additional' => false],
        ];

        $result = $normalizer->mergeOrderCarrierRateIntoContractorsCosts($costs, 30000);

        $this->assertSame($costs, $result);
    }
}
