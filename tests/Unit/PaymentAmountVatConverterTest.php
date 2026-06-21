<?php

namespace Tests\Unit;

use App\Support\PaymentAmountVatConverter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAmountVatConverterTest extends TestCase
{
    #[Test]
    public function dual_presentation_for_no_vat_keeps_net_and_adds_default_gross_hint(): void
    {
        $result = PaymentAmountVatConverter::dualPresentation(100_000.0, 'no_vat');

        $this->assertSame(100_000.0, $result['without_vat']);
        $this->assertGreaterThan(100_000.0, $result['with_vat']);
        $this->assertNotNull($result['vat_label']);
    }

    #[Test]
    public function normalize_to_net_treats_vat_form_amount_as_gross(): void
    {
        $net = PaymentAmountVatConverter::normalizeToNet(120_000.0, 'vat');

        $this->assertEqualsWithDelta(100_000.0, $net, 0.01);
    }

    #[Test]
    public function net_from_gross_amount_converts_using_presentation_rate(): void
    {
        $net = PaymentAmountVatConverter::netFromGrossAmount(120_000.0, 'no_vat');

        $this->assertEqualsWithDelta(100_000.0, $net, 0.01);
    }
}
