<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Support\KpiDeductionRuleAmount;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiDeductionRuleAmountTest extends TestCase
{
    #[Test]
    public function it_calculates_sequential_cash_deduction(): void
    {
        $rule = new KpiDeductionRule([
            'deduction_primary_percent' => 3,
            'deduction_secondary_percent' => 21,
        ]);

        $amount = KpiDeductionRuleAmount::deductionAmount($rule, 100_000);

        $this->assertEqualsWithDelta(23_370, $amount, 0.01);
    }

    #[Test]
    public function it_calculates_single_percent_deduction(): void
    {
        $rule = new KpiDeductionRule([
            'deduction_primary_percent' => 4,
            'deduction_secondary_percent' => null,
        ]);

        $this->assertSame(4000.0, KpiDeductionRuleAmount::deductionAmount($rule, 100_000));
    }
}
