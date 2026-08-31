<?php

namespace Tests\Unit;

use App\Support\OutgoingPaymentRunDateResolver;
use Carbon\Carbon;
use Tests\TestCase;

class OutgoingPaymentRunDateResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_wednesday_planned_suggests_thursday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $this->assertSame(
            '2026-06-04',
            OutgoingPaymentRunDateResolver::suggestForPlannedDate('2026-06-03'),
        );
    }

    public function test_friday_planned_suggests_next_tuesday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $this->assertSame(
            '2026-06-09',
            OutgoingPaymentRunDateResolver::suggestForPlannedDate('2026-06-05'),
        );
    }

    public function test_past_planned_uses_today_floor_before_next_registry_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10'));

        $this->assertSame(
            '2026-06-11',
            OutgoingPaymentRunDateResolver::suggestForPlannedDate('2026-06-01'),
        );
    }

    public function test_tuesday_planned_on_monday_suggests_same_tuesday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08'));

        $this->assertSame(
            '2026-06-09',
            OutgoingPaymentRunDateResolver::suggestForPlannedDate('2026-06-09'),
        );
    }

    public function test_next_specific_weekday_from_thursday_is_same_weeks_thursday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11'));

        $this->assertSame(
            '2026-06-11',
            OutgoingPaymentRunDateResolver::nextSpecificWeekdayFrom(Carbon::today(), 4),
        );
    }

    public function test_customer_party_is_not_outgoing(): void
    {
        $this->assertFalse(OutgoingPaymentRunDateResolver::isOutgoingParty('customer'));
        $this->assertTrue(OutgoingPaymentRunDateResolver::isOutgoingParty('carrier'));
        $this->assertTrue(OutgoingPaymentRunDateResolver::isOutgoingParty('contractor'));
    }
}
