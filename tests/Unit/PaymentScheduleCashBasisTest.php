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
        $this->assertSame('unloading', PaymentScheduleCashBasis::effectiveBasis('cash', 'fttn', 'carrier'));
    }

    #[Test]
    public function it_maps_carrier_cash_ottn_and_fttn_receipt_to_waybill(): void
    {
        $this->assertSame('waybill', PaymentScheduleCashBasis::effectiveBasis('cash', 'ottn', 'carrier'));
        $this->assertSame('waybill', PaymentScheduleCashBasis::effectiveBasis('cash', 'fttn_receipt', 'carrier'));
        $this->assertSame('waybill', PaymentScheduleCashBasis::effectiveBasis('cash', 'ottn', 'contractor'));
    }

    #[Test]
    public function it_keeps_customer_cash_ottn_and_fttn_receipt(): void
    {
        $this->assertSame('ottn', PaymentScheduleCashBasis::effectiveBasis('cash', 'ottn', 'customer'));
        $this->assertSame('fttn_receipt', PaymentScheduleCashBasis::effectiveBasis('cash', 'fttn_receipt', 'customer'));
        $this->assertSame('ottn', PaymentScheduleCashBasis::effectiveBasis('cash', 'ottn'));
    }

    #[Test]
    public function it_keeps_explicit_unloading_for_cash(): void
    {
        $this->assertSame('unloading', PaymentScheduleCashBasis::effectiveBasis('cash', 'unloading', 'carrier'));
    }

    #[Test]
    public function it_does_not_change_basis_for_non_cash(): void
    {
        $this->assertSame('fttn', PaymentScheduleCashBasis::effectiveBasis('vat_22', 'fttn', 'carrier'));
        $this->assertSame('ottn', PaymentScheduleCashBasis::effectiveBasis(null, 'ottn', 'carrier'));
    }
}
