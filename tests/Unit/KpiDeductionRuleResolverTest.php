<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Services\KpiDeductionRuleResolver;
use App\Support\KpiDeductionCarrierRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiDeductionRuleResolverTest extends TestCase
{
    #[Test]
    public function it_uses_legacy_categories_before_cutoff_date(): void
    {
        $resolver = app(KpiDeductionRuleResolver::class);

        $result = $resolver->resolve('2026-05-31', 'vat_22', ['cash']);

        $this->assertFalse($result['uses_custom_rules']);
        $this->assertSame('cash', $result['deal_type']);
    }

    #[Test]
    public function it_uses_custom_rules_on_and_after_cutoff_date(): void
    {
        KpiDeductionRule::query()->create([
            'name' => 'Наличка',
            'priority' => 400,
            'carrier_rule' => KpiDeductionCarrierRule::ALL_CASH,
            'deduction_primary_percent' => 3,
            'deduction_secondary_percent' => 21,
            'effective_from' => '2026-06-01',
            'is_active' => true,
        ]);

        $resolver = app(KpiDeductionRuleResolver::class);
        $result = $resolver->resolve('2026-06-01', 'vat_22', ['cash']);

        $this->assertTrue($result['uses_custom_rules']);
        $this->assertStringStartsWith('rule:', $result['deal_type']);
        $this->assertSame('Наличка', $result['deal_type_label']);
    }

    #[Test]
    public function it_returns_unknown_when_no_custom_rule_matches(): void
    {
        KpiDeductionRule::query()->delete();

        $resolver = app(KpiDeductionRuleResolver::class);
        $result = $resolver->resolve('2026-06-01', 'vat_22', ['vat_22']);

        $this->assertTrue($result['uses_custom_rules']);
        $this->assertSame('unknown', $result['deal_type']);
    }
}
