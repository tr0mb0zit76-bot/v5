<?php

namespace Tests\Unit;

use App\Services\KpiConfigurationService;
use App\Services\OrderCompensationService;
use App\Services\SalesMarginCounterService;
use Tests\TestCase;

class SalesMarginCounterServiceTest extends TestCase
{
    public function test_calculates_three_scenarios_from_independent_fields(): void
    {
        $compensation = $this->createMock(OrderCompensationService::class);
        $compensation->method('calculateMarginScenario')->willReturn([
            'delta' => 100_000.0,
            'kpi_percent' => 12.0,
            'salary_accrued' => 5000.0,
            'projected_direct_ratio' => 0.5,
            'period_orders_before' => 1,
            'period_direct_before' => 1,
            'period_orders_after' => 2,
            'period_direct_after' => 2,
        ]);

        $kpi = $this->createMock(KpiConfigurationService::class);
        $kpi->method('getBonusMultiplier')->willReturn(1.0);

        $service = new SalesMarginCounterService($compensation, $kpi);

        $result = $service->calculate([
            'manager_id' => 1,
            'order_date' => '2026-05-28',
            'customer_without_vat' => 1_000_000,
            'customer_with_vat' => 1_220_000,
            'carrier_without_vat' => 800_000,
            'carrier_with_vat' => 976_000,
            'bonus' => 0,
            'additional_expenses' => 0,
        ]);

        $this->assertCount(3, $result['scenarios']);
        $this->assertSame('direct_with_vat', $result['scenarios'][0]['scenario_key']);
        $this->assertSame('direct_without_vat', $result['scenarios'][1]['scenario_key']);
        $this->assertSame('indirect', $result['scenarios'][2]['scenario_key']);
        $this->assertSame(100_000.0, $result['scenarios'][0]['margin']);
        $this->assertArrayNotHasKey('fields', $result);
    }

    public function test_direct_with_vat_scenario_requires_with_vat_columns(): void
    {
        $compensation = $this->createMock(OrderCompensationService::class);
        $compensation->method('calculateMarginScenario')->willReturn([
            'delta' => 50_000.0,
            'kpi_percent' => 10.0,
            'salary_accrued' => 0.0,
            'projected_direct_ratio' => 0.5,
            'period_orders_before' => 0,
            'period_direct_before' => 0,
            'period_orders_after' => 1,
            'period_direct_after' => 1,
        ]);

        $kpi = $this->createMock(KpiConfigurationService::class);
        $kpi->method('getBonusMultiplier')->willReturn(1.0);

        $service = new SalesMarginCounterService($compensation, $kpi);

        $result = $service->calculate([
            'manager_id' => 1,
            'order_date' => '2026-05-28',
            'customer_without_vat' => 1_000_000,
            'carrier_without_vat' => 800_000,
        ]);

        $this->assertNull($result['scenarios'][0]['margin']);
        $this->assertNotNull($result['scenarios'][1]['margin']);
        $this->assertNull($result['scenarios'][2]['margin']);
    }

    public function test_indirect_scenario_requires_customer_with_vat_only(): void
    {
        $compensation = $this->createMock(OrderCompensationService::class);
        $compensation->expects($this->once())
            ->method('calculateMarginScenario')
            ->willReturn([
                'delta' => 22_100.0,
                'kpi_percent' => 3.0,
                'salary_accrued' => 0.0,
                'projected_direct_ratio' => 0.5,
                'period_orders_before' => 0,
                'period_direct_before' => 0,
                'period_orders_after' => 1,
                'period_direct_after' => 1,
            ]);

        $kpi = $this->createMock(KpiConfigurationService::class);
        $kpi->method('getBonusMultiplier')->willReturn(1.0);

        $service = new SalesMarginCounterService($compensation, $kpi);

        $result = $service->calculate([
            'manager_id' => 1,
            'order_date' => '2026-05-28',
            'customer_without_vat' => 120_000,
            'carrier_without_vat' => 80_000,
            'bonus' => 10_000,
            'additional_expenses' => 0,
        ]);

        $this->assertNull($result['scenarios'][2]['margin']);
        $this->assertSame('Укажите сумму заказчика для этого варианта.', $result['scenarios'][2]['comment']);
    }
}
