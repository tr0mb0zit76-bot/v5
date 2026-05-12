<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Сдвиг даты: календарные сутки либо «банковские дни» (пн–пт, без праздников РФ).
 */
final class CalendarBankDayShifter
{
    public const UNIT_CALENDAR = 'calendar_days';

    public const UNIT_BANK = 'bank_days';

    /**
     * @param  self::UNIT_*  $unit
     */
    public static function shift(Carbon $anchor, int $offsetDays, string $unit): Carbon
    {
        $unitNorm = strtolower(trim($unit)) === self::UNIT_BANK ? self::UNIT_BANK : self::UNIT_CALENDAR;

        $day = $anchor->copy()->startOfDay();

        if ($unitNorm === self::UNIT_CALENDAR) {
            return $day->addDays($offsetDays);
        }

        return $offsetDays >= 0
            ? self::addWeekdaysForward($day, $offsetDays)
            : self::addWeekdaysBackward($day, -$offsetDays);
    }

    private static function addWeekdaysForward(Carbon $date, int $n): Carbon
    {
        $result = $date->copy();
        $added = 0;

        while ($added < $n) {
            $result->addDay();
            if ($result->isWeekday()) {
                $added++;
            }
        }

        return $result;
    }

    private static function addWeekdaysBackward(Carbon $date, int $n): Carbon
    {
        $result = $date->copy();
        $subtracted = 0;

        while ($subtracted < $n) {
            $result->subDay();
            if ($result->isWeekday()) {
                $subtracted++;
            }
        }

        return $result;
    }
}
