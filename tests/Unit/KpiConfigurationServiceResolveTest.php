<?php

namespace Tests\Unit;

use App\Services\KpiConfigurationService;
use PHPUnit\Framework\TestCase;

class KpiConfigurationServiceResolveTest extends TestCase
{
    public function test_when_two_ranges_overlap_at_boundary_picks_row_with_higher_threshold_from(): void
    {
        $mock = $this->getMockBuilder(KpiConfigurationService::class)
            ->onlyMethods(['groupedThresholds'])
            ->getMock();

        $mock->method('groupedThresholds')->willReturn([
            ['threshold_from' => 0.0, 'threshold_to' => 0.5, 'direct_kpi' => 7, 'indirect_kpi' => 11],
            ['threshold_from' => 0.5, 'threshold_to' => 1.0, 'direct_kpi' => 3, 'indirect_kpi' => 7],
        ]);

        $this->assertSame(3.0, $mock->resolveKpiPercentForDeal('direct', 0.5));
        $this->assertSame(3.0, $mock->resolveKpiPercentForDeal('direct', 1.0));
        $this->assertSame(7.0, $mock->resolveKpiPercentForDeal('direct', 0.25));
    }

    public function test_single_match_uses_that_row(): void
    {
        $mock = $this->getMockBuilder(KpiConfigurationService::class)
            ->onlyMethods(['groupedThresholds'])
            ->getMock();

        $mock->method('groupedThresholds')->willReturn([
            ['threshold_from' => 0.0, 'threshold_to' => 0.24, 'direct_kpi' => 3, 'indirect_kpi' => 7],
            ['threshold_from' => 0.25, 'threshold_to' => 0.49, 'direct_kpi' => 4, 'indirect_kpi' => 8],
            ['threshold_from' => 0.5, 'threshold_to' => 1.0, 'direct_kpi' => 5, 'indirect_kpi' => 9],
        ]);

        $this->assertSame(5.0, $mock->resolveKpiPercentForDeal('direct', 1.0));
        $this->assertSame(9.0, $mock->resolveKpiPercentForDeal('indirect', 1.0));
    }
}
