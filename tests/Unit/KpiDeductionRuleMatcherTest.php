<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Support\KpiDeductionCarrierRule;
use App\Support\KpiDeductionRuleMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiDeductionRuleMatcherTest extends TestCase
{
    #[Test]
    public function it_matches_cash_rule_when_all_carriers_are_cash(): void
    {
        $rule = new KpiDeductionRule([
            'carrier_rule' => KpiDeductionCarrierRule::ALL_CASH,
        ]);

        $this->assertTrue(KpiDeductionRuleMatcher::matches($rule, 'vat_22', ['cash', 'cash']));
        $this->assertFalse(KpiDeductionRuleMatcher::matches($rule, 'vat_22', ['cash', 'vat_22']));
    }

    #[Test]
    public function it_matches_vat_zero_customer_and_carrier_22_rule(): void
    {
        $rule = new KpiDeductionRule([
            'customer_vat_rate_percent' => 0,
            'carrier_rule' => KpiDeductionCarrierRule::ANY_VAT_RATE,
            'carrier_vat_rate_percent' => 22,
        ]);

        $this->assertTrue(KpiDeductionRuleMatcher::matches($rule, 'vat_0', ['vat_22']));
        $this->assertFalse(KpiDeductionRuleMatcher::matches($rule, 'vat_22', ['vat_22']));
    }

    #[Test]
    public function it_matches_all_positive_vat_rule(): void
    {
        $rule = new KpiDeductionRule([
            'customer_positive_vat_required' => true,
            'carrier_rule' => KpiDeductionCarrierRule::ALL_POSITIVE_VAT,
        ]);

        $this->assertTrue(KpiDeductionRuleMatcher::matches($rule, 'vat_22', ['vat_22', 'vat_20']));
        $this->assertFalse(KpiDeductionRuleMatcher::matches($rule, 'vat_22', ['no_vat']));
    }
}
