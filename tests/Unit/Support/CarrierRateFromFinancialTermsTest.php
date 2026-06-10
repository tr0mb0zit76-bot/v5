<?php

namespace Tests\Unit\Support;

use App\Support\CarrierRateFromFinancialTerms;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarrierRateFromFinancialTermsTest extends TestCase
{
    #[Test]
    public function it_sums_only_carrier_leg_rows(): void
    {
        $sum = CarrierRateFromFinancialTerms::sumContractorsCostsAmounts([
            ['payment_form' => 'vat_22', 'amount' => 100000, 'stage' => 'leg_1'],
            ['payment_form' => 'cash', 'amount' => 7000, 'stage' => 'additional_1', 'is_additional' => true],
        ]);

        $this->assertSame(100000.0, $sum);
    }
}
