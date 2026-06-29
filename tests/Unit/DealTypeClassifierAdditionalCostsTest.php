<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Services\DealTypeClassifier;
use App\Support\KpiDeductionCarrierRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DealTypeClassifierAdditionalCostsTest extends TestCase
{
    #[Test]
    public function it_ignores_additional_performer_payment_forms_when_matching_deduction_rules(): void
    {
        KpiDeductionRule::query()->create([
            'name' => 'НДС 0 / 22',
            'priority' => 500,
            'customer_vat_rate_percent' => 0,
            'carrier_rule' => KpiDeductionCarrierRule::ANY_VAT_RATE,
            'carrier_vat_rate_percent' => 22,
            'deduction_primary_percent' => 5,
            'margin_supplement_percent' => 0,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $classifier = app(DealTypeClassifier::class);

        $resolution = $classifier->resolve([
            'customer_payment_form' => 'vat_0',
            'order_date' => '2026-06-10',
            'contractors_costs' => [
                ['payment_form' => 'vat_22', 'amount' => 100000, 'stage' => 'leg_1'],
                [
                    'payment_form' => 'cash',
                    'amount' => 5000,
                    'stage' => 'additional_1',
                    'is_additional' => true,
                ],
            ],
        ]);

        $this->assertNotSame('unknown', $resolution['deal_type']);
        $this->assertTrue($resolution['uses_custom_rules']);
    }
}
