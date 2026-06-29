<?php

namespace Tests\Unit;

use App\Services\DealTypeClassifier;
use App\Support\PaymentFormVat;
use App\Support\VatZeroCustomerStandardVatCarrierMarginSupplement;
use Tests\TestCase;

class DealTypeClassifierTest extends TestCase
{
    public function test_vat_5_and_vat_22_carrier_with_vat_0_customer_is_vat_zero_22_category(): void
    {
        $type = app(DealTypeClassifier::class)->classify([
            'customer_payment_form' => 'vat_0',
            'contractors_costs' => [
                ['payment_form' => 'vat_0', 'amount' => 840_000],
                ['payment_form' => 'vat_22', 'amount' => 600_000],
                ['payment_form' => 'vat_22', 'amount' => 480_000],
            ],
        ]);

        $this->assertSame('vat_zero_22', $type);
        $this->assertFalse(PaymentFormVat::isIndirectDeal('vat_0', ['vat_0', 'vat_22', 'vat_22']));
    }

    public function test_vat_customer_and_no_vat_carrier_is_indirect(): void
    {
        $this->assertTrue(PaymentFormVat::isIndirectDeal('vat_22', ['no_vat']));
        $this->assertSame('vat', app(DealTypeClassifier::class)->classify([
            'customer_payment_form' => 'vat_22',
            'contractors_costs' => [
                ['payment_form' => 'no_vat', 'amount' => 100_000],
            ],
        ]));
    }

    public function test_no_vat_customer_and_vat_carrier_is_indirect(): void
    {
        $this->assertTrue(PaymentFormVat::isIndirectDeal('no_vat', ['vat_22']));
    }

    public function test_vat_5_and_vat_22_is_not_indirect(): void
    {
        $this->assertFalse(PaymentFormVat::isIndirectDeal('vat_5', ['vat_22']));
    }

    public function test_margin_supplement_matches_user_example(): void
    {
        $supplement = VatZeroCustomerStandardVatCarrierMarginSupplement::amount('vat_0', [
            ['payment_form' => 'vat_0', 'amount' => 840_000],
            ['payment_form' => 'vat_22', 'amount' => 600_000],
            ['payment_form' => 'vat_22', 'amount' => 480_000],
        ]);

        $this->assertSame(162_000.0, $supplement);

        $customerRate = 2_240_000;
        $kpiPercent = 3.0;
        $expense = 840_000 + 1_080_000;
        $delta = $customerRate - ($customerRate * ($kpiPercent / 100)) - $expense + $supplement;

        $this->assertEqualsWithDelta(414_800, $delta, 1.0);
    }
}
