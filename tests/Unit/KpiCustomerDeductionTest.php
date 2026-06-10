<?php

namespace Tests\Unit;

use App\Support\KpiCustomerDeduction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiCustomerDeductionTest extends TestCase
{
    /**
     * @return array{
     *     vat_percent: float,
     *     vat_all_percent: float,
     *     vat_zero_22_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     *     vat_zero_cash_primary_percent: float,
     *     vat_zero_cash_secondary_percent: float,
     * }
     */
    private function rates(): array
    {
        return [
            'vat_percent' => 3.0,
            'vat_all_percent' => 4.0,
            'vat_zero_22_percent' => 3.0,
            'cash_primary_percent' => 3.0,
            'cash_secondary_percent' => 21.0,
            'vat_zero_cash_primary_percent' => 4.0,
            'vat_zero_cash_secondary_percent' => 16.0,
        ];
    }

    #[Test]
    public function it_applies_vat_all_percent_for_vat_all_category(): void
    {
        $amount = KpiCustomerDeduction::amount(100_000.0, 'vat_all', $this->rates());

        $this->assertSame(4_000.0, $amount);
    }

    #[Test]
    public function it_applies_vat_percent_for_generic_vat_category(): void
    {
        $amount = KpiCustomerDeduction::amount(100_000.0, 'vat', $this->rates());

        $this->assertSame(3_000.0, $amount);
    }

    #[Test]
    public function it_applies_sequential_deduction_for_vat_zero_cash_category(): void
    {
        $amount = KpiCustomerDeduction::amount(100_000.0, 'vat_zero_cash', $this->rates());

        $this->assertSame(19_360.0, $amount);
    }
}
