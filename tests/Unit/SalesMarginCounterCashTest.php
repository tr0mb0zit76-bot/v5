<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Services\SalesMarginCounterService;
use App\Support\CashToCashMarginCalculator;
use App\Support\KpiDeductionCarrierRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesMarginCounterCashTest extends TestCase
{
    #[Test]
    public function cash_to_cash_margin_applies_kpi_percent_before_subtracting_costs(): void
    {
        $this->assertSame(
            5_000.0,
            CashToCashMarginCalculator::margin(100_000.0, 80_000.0, 15.0, true),
        );

        $this->assertSame(
            5_000.0,
            CashToCashMarginCalculator::margin(100_000.0, 80_000.0, 15.0, false),
        );
    }

    #[Test]
    public function cash_to_cash_detector_requires_cash_on_all_sides(): void
    {
        $this->assertTrue(CashToCashMarginCalculator::isCashToCash('cash', [
            ['payment_form' => 'cash', 'amount' => 80_000.0],
        ]));

        $this->assertFalse(CashToCashMarginCalculator::isCashToCash('cash', [
            ['payment_form' => 'bank_transfer', 'amount' => 80_000.0],
        ]));
    }

    #[Test]
    public function counter_service_calculates_margin_for_all_cash_carrier_rule(): void
    {
        $rule = KpiDeductionRule::query()->create([
            'name' => 'Наличка у перевозчика',
            'priority' => 100,
            'customer_payment_form' => 'vat_22',
            'carrier_rule' => KpiDeductionCarrierRule::ALL_CASH,
            'deduction_primary_percent' => 4,
            'deduction_secondary_percent' => 16,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $service = app(SalesMarginCounterService::class);

        $result = $service->calculate([
            'kpi_deduction_rule_id' => $rule->id,
            'customer_rate' => 100_000.0,
            'carrier_rate' => 80_000.0,
            'bonus' => 0,
            'additional_expenses' => 0,
        ]);

        $this->assertSame('4% + 16%', $result['summary']['kpi_deduction_rates_label']);
        $this->assertSame(19_360.0, $result['summary']['kpi_deduction_amount']);
        $this->assertSame(640.0, $result['summary']['margin']);
    }
}
