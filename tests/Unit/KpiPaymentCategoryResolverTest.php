<?php

namespace Tests\Unit;

use App\Support\KpiPaymentCategoryResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KpiPaymentCategoryResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_vat_all_when_customer_and_all_carriers_have_vat(): void
    {
        $category = KpiPaymentCategoryResolver::resolve('vat_22', ['vat_22']);

        $this->assertSame('vat_all', $category);
    }

    #[Test]
    public function it_resolves_vat_all_for_multi_leg_positive_vat_deal(): void
    {
        $category = KpiPaymentCategoryResolver::resolve('vat_22', ['vat_22', 'vat_5']);

        $this->assertSame('vat_all', $category);
    }

    #[Test]
    public function it_keeps_vat_zero_22_before_vat_all(): void
    {
        $category = KpiPaymentCategoryResolver::resolve('vat_0', ['vat_22']);

        $this->assertSame('vat_zero_22', $category);
    }

    #[Test]
    public function it_resolves_vat_zero_cash_when_customer_vat_zero_and_carrier_cash(): void
    {
        $category = KpiPaymentCategoryResolver::resolve('vat_0', ['cash']);

        $this->assertSame('vat_zero_cash', $category);
    }

    #[Test]
    public function it_resolves_generic_vat_for_zero_zero_combination(): void
    {
        $category = KpiPaymentCategoryResolver::resolve('vat_0', ['vat_0']);

        $this->assertSame('vat', $category);
    }

    #[Test]
    public function it_resolves_generic_vat_when_customer_has_vat_and_carrier_has_no_vat(): void
    {
        $category = KpiPaymentCategoryResolver::resolve('vat_22', ['no_vat']);

        $this->assertSame('vat', $category);
    }
}
