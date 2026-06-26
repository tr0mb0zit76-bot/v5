<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleCashBasis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentScheduleCashBasisTest extends TestCase
{
    #[Test]
    public function it_maps_fttn_to_unloading_for_cash_only(): void
    {
        $this->assertSame('unloading', PaymentScheduleCashBasis::effectiveBasis('cash', 'fttn'));
    }

    #[Test]
    public function it_keeps_ottn_and_fttn_receipt_for_cash(): void
    {
        $this->assertSame('ottn', PaymentScheduleCashBasis::effectiveBasis('cash', 'ottn'));
        $this->assertSame('fttn_receipt', PaymentScheduleCashBasis::effectiveBasis('cash', 'fttn_receipt'));
    }

    #[Test]
    public function it_keeps_explicit_unloading_for_cash(): void
    {
        $this->assertSame('unloading', PaymentScheduleCashBasis::effectiveBasis('cash', 'unloading'));
    }

    #[Test]
    public function it_does_not_change_basis_for_non_cash(): void
    {
        $this->assertSame('fttn', PaymentScheduleCashBasis::effectiveBasis('vat_22', 'fttn'));
        $this->assertSame('ottn', PaymentScheduleCashBasis::effectiveBasis(null, 'ottn'));
    }
}
