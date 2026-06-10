<?php

namespace Tests\Unit;

use App\Services\SalesMarginCounterService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesMarginCounterServiceTest extends TestCase
{
    #[Test]
    public function it_includes_vat_zero_cash_scenario_with_default_deduction_rates(): void
    {
        $service = app(SalesMarginCounterService::class);

        $result = $service->calculate([
            'manager_id' => 0,
            'order_date' => null,
            'customer_rate' => 100_000,
            'carrier_cash_rate' => 80_000,
            'carrier_cashless_rate' => 75_000,
        ]);

        $keys = array_column($result['scenarios'], 'scenario_key');

        $this->assertContains(SalesMarginCounterService::SCENARIO_VAT_ZERO_CASH, $keys);

        $scenario = collect($result['scenarios'])
            ->firstWhere('scenario_key', SalesMarginCounterService::SCENARIO_VAT_ZERO_CASH);

        $this->assertSame('vat_zero_cash', $scenario['deal_type']);
        $this->assertSame('4% + 16%', $scenario['kpi_deduction_rates_label']);
        $this->assertSame(19_360.0, $scenario['kpi_deduction_amount']);
    }
}
