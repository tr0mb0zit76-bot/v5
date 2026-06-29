<?php

namespace Tests\Unit;

use App\Models\KpiDeductionRule;
use App\Services\SalesMarginCounterService;
use App\Support\KpiDeductionCarrierRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesMarginCounterServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_margin_for_selected_deduction_rule(): void
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
            'customer_rate' => 100_000,
            'carrier_rate' => 80_000,
            'bonus' => 0,
            'additional_expenses' => 0,
        ]);

        $this->assertSame('4% + 16%', $result['summary']['kpi_deduction_rates_label']);
        $this->assertSame(19_360.0, $result['summary']['kpi_deduction_amount']);
        $this->assertSame(640.0, $result['summary']['margin']);
    }
}
