<?php

namespace Tests\Unit;

use App\Support\MarginCounterAmountResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarginCounterAmountResolverTest extends TestCase
{
    #[Test]
    public function negotiation_uses_customer_gross_and_carrier_net_for_vat_no_vat_pair(): void
    {
        $customer = MarginCounterAmountResolver::customerRevenue(
            'customer_with_vat',
            null,
            150000.0,
            'vat_22',
            MarginCounterAmountResolver::BASIS_NEGOTIATION,
        );

        $carrier = MarginCounterAmountResolver::carrierExpense(
            'carrier_without_vat',
            80000.0,
            97600.0,
            'no_vat',
            MarginCounterAmountResolver::BASIS_NEGOTIATION,
        );

        $this->assertSame(150000.0, $customer);
        $this->assertSame(80000.0, $carrier);
    }

    #[Test]
    public function negotiation_vat_carrier_uses_with_vat_even_when_anchor_is_without(): void
    {
        $carrier = MarginCounterAmountResolver::carrierExpense(
            'carrier_without_vat',
            80000.0,
            97600.0,
            'vat_22',
            MarginCounterAmountResolver::BASIS_NEGOTIATION,
        );

        $this->assertSame(97600.0, $carrier);
    }

    #[Test]
    public function order_net_converts_customer_vat_gross_to_net(): void
    {
        $customer = MarginCounterAmountResolver::customerRevenue(
            'customer_with_vat',
            null,
            150000.0,
            'vat_22',
            MarginCounterAmountResolver::BASIS_ORDER_NET,
        );

        $this->assertNotNull($customer);
        $this->assertLessThan(150000.0, $customer);
        $this->assertGreaterThan(120000.0, $customer);
    }
}
