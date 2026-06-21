<?php

namespace Tests\Unit;

use App\Services\SalaryPayrollService;
use Tests\TestCase;

class SalaryPayrollServiceTest extends TestCase
{
    public function test_normalize_half_month_dates_for_h1(): void
    {
        $normalized = app(SalaryPayrollService::class)->normalizeHalfMonthDates('h1', '2026-05-12');

        $this->assertSame('2026-05-01', $normalized['period_start']);
        $this->assertSame('2026-05-15', $normalized['period_end']);
    }

    public function test_normalize_half_month_dates_for_h2(): void
    {
        $normalized = app(SalaryPayrollService::class)->normalizeHalfMonthDates('h2', '2026-05-20');

        $this->assertSame('2026-05-16', $normalized['period_start']);
        $this->assertSame('2026-05-31', $normalized['period_end']);
    }

    public function test_normalize_half_month_dates_for_february_h2(): void
    {
        $normalized = app(SalaryPayrollService::class)->normalizeHalfMonthDates('h2', '2024-02-20');

        $this->assertSame('2024-02-16', $normalized['period_start']);
        $this->assertSame('2024-02-29', $normalized['period_end']);
    }
}
