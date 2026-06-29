<?php

namespace Tests\Unit;

use App\Services\KpiConfigurationService;
use Tests\TestCase;

class KpiConfigurationServiceResolveTest extends TestCase
{
    public function test_cash_category_uses_sequential_primary_and_secondary_deduction(): void
    {
        $service = app(KpiConfigurationService::class);

        $amount = $service->kpiDeductionAmount(100_000.0, 'cash');
        $percent = $service->effectiveKpiPercent(100_000.0, 'cash');

        $this->assertSame(23_370.0, round($amount, 2));
        $this->assertSame(23.37, $percent);
    }

    public function test_vat_all_category_uses_single_percent_deduction(): void
    {
        $service = app(KpiConfigurationService::class);

        $amount = $service->kpiDeductionAmount(100_000.0, 'vat_all');
        $percent = $service->effectiveKpiPercent(100_000.0, 'vat_all');

        $this->assertSame(4_000.0, round($amount, 2));
        $this->assertSame(4.0, $percent);
    }
}
