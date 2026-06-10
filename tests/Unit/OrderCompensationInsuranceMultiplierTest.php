<?php

namespace Tests\Unit;

use App\Services\OrderCompensationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCompensationInsuranceMultiplierTest extends TestCase
{
    #[Test]
    public function it_applies_default_insurance_multiplier_in_realtime_margin_formula(): void
    {
        $service = app(OrderCompensationService::class);

        $result = $service->calculateRealtime([
            'customer_rate' => 1000,
            'carrier_rate' => 400,
            'additional_expenses' => 0,
            'insurance' => 100,
            'bonus' => 0,
            'manager_id' => 1,
            'order_date' => '2026-05-31',
            'customer_payment_form' => 'vat_22',
            'contractors_costs' => [
                ['payment_form' => 'vat_22', 'amount' => 400],
            ],
        ]);

        $this->assertSame('vat_all', $result['deal_type']);
        $this->assertSame(440.0, $result['delta']);
    }
}
