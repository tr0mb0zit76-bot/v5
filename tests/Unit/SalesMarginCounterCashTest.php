<?php

namespace Tests\Unit;

use App\Services\DealTypeClassifier;
use App\Services\KpiConfigurationService;
use App\Services\OrderCompensationService;
use App\Services\OrderDocumentRequirementService;
use App\Services\PeriodCalculator;
use App\Services\SalesMarginCounterService;
use App\Support\CashToCashMarginCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesMarginCounterCashTest extends TestCase
{
    #[Test]
    public function cash_to_cash_margin_is_income_minus_expense_without_kpi(): void
    {
        $this->assertSame(
            20_000.0,
            CashToCashMarginCalculator::margin(100_000.0, 80_000.0, 15.0, true),
        );

        $this->assertSame(
            5_000.0,
            CashToCashMarginCalculator::margin(100_000.0, 80_000.0, 15.0, false),
        );
    }

    #[Test]
    public function order_compensation_realtime_skips_kpi_for_cash_to_cash(): void
    {
        $periodCalculator = $this->createMock(PeriodCalculator::class);
        $periodCalculator->method('getPeriodForDate')->willReturn([
            'start' => '2026-05-01',
            'end' => '2026-05-31',
        ]);
        $periodCalculator->method('getManagerPeriodStats')->willReturn([
            'direct_ratio' => 0.5,
        ]);

        $kpiConfiguration = $this->createMock(KpiConfigurationService::class);
        $kpiConfiguration->method('resolveKpiPercentForDeal')->willReturn(15.0);
        $kpiConfiguration->method('getBonusMultiplier')->willReturn(1.0);

        $service = new OrderCompensationService(
            new DealTypeClassifier,
            $periodCalculator,
            $kpiConfiguration,
            $this->createMock(OrderDocumentRequirementService::class),
        );

        $result = $service->calculateRealtime([
            'customer_rate' => 100_000.0,
            'carrier_rate' => 80_000.0,
            'manager_id' => 1,
            'order_date' => '2026-05-19',
            'customer_payment_form' => 'cash',
            'carrier_payment_form' => 'cash',
            'contractors_costs' => [
                ['payment_form' => 'cash', 'amount' => 80_000.0],
            ],
        ]);

        $this->assertSame(20_000.0, $result['delta']);
    }

    #[Test]
    public function counter_service_uses_income_minus_expense_for_cash_to_cash(): void
    {
        $compensation = $this->createMock(OrderCompensationService::class);
        $compensation->method('calculateRealtime')->willReturnCallback(function (array $data): array {
            if ((float) ($data['customer_rate'] ?? 0) === 1.0) {
                return [
                    'kpi_percent' => 15.0,
                    'delta' => 0.0,
                    'salary_accrued' => 0.0,
                    'deal_type' => 'direct',
                ];
            }

            $cashToCash = CashToCashMarginCalculator::isCashToCash(
                $data['customer_payment_form'] ?? null,
                is_array($data['contractors_costs'] ?? null) ? $data['contractors_costs'] : [],
            );
            $expense = (float) ($data['carrier_rate'] ?? 0)
                + (float) ($data['additional_expenses'] ?? 0)
                + (float) ($data['insurance'] ?? 0)
                + (float) ($data['bonus'] ?? 0);
            $customerRate = (float) ($data['customer_rate'] ?? 0);
            $kpiPercent = 15.0;
            $delta = $cashToCash
                ? ($customerRate - $expense)
                : ($customerRate - ($customerRate * ($kpiPercent / 100)) - $expense);

            return [
                'kpi_percent' => $kpiPercent,
                'delta' => round($delta, 2),
                'salary_accrued' => 0.0,
                'deal_type' => 'direct',
            ];
        });

        $kpi = $this->createMock(KpiConfigurationService::class);
        $kpi->method('getBonusMultiplier')->willReturn(1.0);

        $service = new SalesMarginCounterService($compensation, $kpi);

        $result = $service->calculate([
            'anchor_field' => SalesMarginCounterService::ANCHOR_CUSTOMER_WITHOUT_VAT,
            'customer_without_vat' => 100_000.0,
            'carrier_without_vat' => 80_000.0,
            'manager_id' => 1,
            'order_date' => '2026-05-19',
            'customer_payment_form' => 'cash',
            'carrier_payment_form' => 'cash',
            'min_margin_percent' => 10,
        ]);

        $this->assertTrue($result['cash_to_cash']);
        $this->assertSame(20_000.0, $result['fields']['margin']);
        $this->assertStringContainsString('KPI не вычитается', implode(' ', $result['summary']['hints']));
    }
}
