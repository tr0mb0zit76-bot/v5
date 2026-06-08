<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\DispositionUnclosedTrip;
use Carbon\Carbon;
use Tests\TestCase;

class DispositionUnclosedTripTest extends TestCase
{
    public function test_scroll_anchor_prefers_today_when_inside_range(): void
    {
        $today = '2026-05-28';
        $dates = ['2026-05-20', '2026-05-21', '2026-05-28', '2026-06-01'];

        $anchor = DispositionUnclosedTrip::resolveScrollAnchorDate($dates, $today);

        $this->assertSame('2026-05-28', $anchor);
    }

    public function test_scroll_anchor_clamps_to_range_start_when_today_before_range(): void
    {
        $today = '2026-05-10';
        $dates = ['2026-05-20', '2026-05-21', '2026-05-28'];

        $anchor = DispositionUnclosedTrip::resolveScrollAnchorDate($dates, $today);

        $this->assertSame('2026-05-20', $anchor);
    }

    public function test_scroll_anchor_clamps_to_range_end_when_today_after_range(): void
    {
        $today = '2026-07-01';
        $dates = ['2026-05-20', '2026-05-21', '2026-05-28'];

        $anchor = DispositionUnclosedTrip::resolveScrollAnchorDate($dates, $today);

        $this->assertSame('2026-05-28', $anchor);
    }

    public function test_is_unclosed_by_schedule_when_unloading_in_future(): void
    {
        $order = new Order([
            'loading_date' => Carbon::parse('2026-05-20'),
            'unloading_date' => Carbon::parse('2026-06-05'),
        ]);

        $this->assertTrue(DispositionUnclosedTrip::isUnclosedBySchedule($order, '2026-05-28'));
    }

    public function test_is_closed_by_schedule_when_unloading_in_past(): void
    {
        $order = new Order([
            'loading_date' => Carbon::parse('2026-05-01'),
            'unloading_date' => Carbon::parse('2026-05-10'),
        ]);

        $this->assertFalse(DispositionUnclosedTrip::isUnclosedBySchedule($order, '2026-05-28'));
    }
}
