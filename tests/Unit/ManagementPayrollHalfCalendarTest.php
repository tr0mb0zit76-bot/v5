<?php

namespace Tests\Unit;

use App\Support\ManagementPayrollHalfCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagementPayrollHalfCalendarTest extends TestCase
{
    #[Test]
    public function it_resolves_first_half_for_day_ten(): void
    {
        $definition = ManagementPayrollHalfCalendar::resolveForDate(CarbonImmutable::parse('2026-06-10'));

        $this->assertSame(2026, $definition['year']);
        $this->assertSame(6, $definition['month']);
        $this->assertSame(1, $definition['half']);
        $this->assertSame('2026-06-01', $definition['period_start']);
        $this->assertSame('2026-06-15', $definition['period_end']);
        $this->assertSame('2026-06-20', $definition['payment_date']);
    }

    #[Test]
    public function it_resolves_second_half_for_day_twenty_five(): void
    {
        $definition = ManagementPayrollHalfCalendar::resolveForDate(CarbonImmutable::parse('2026-06-25'));

        $this->assertSame(2026, $definition['year']);
        $this->assertSame(6, $definition['month']);
        $this->assertSame(2, $definition['half']);
        $this->assertSame('2026-06-16', $definition['period_start']);
        $this->assertSame('2026-06-30', $definition['period_end']);
        $this->assertSame('2026-07-05', $definition['payment_date']);
    }

    #[Test]
    public function it_resolves_half_from_payment_date_on_fifth(): void
    {
        $definition = ManagementPayrollHalfCalendar::resolveForPaymentDate(CarbonImmutable::parse('2026-07-05'));

        $this->assertSame(2, $definition['half']);
        $this->assertSame('2026-06-16', $definition['period_start']);
        $this->assertSame('2026-06-30', $definition['period_end']);
    }
}
