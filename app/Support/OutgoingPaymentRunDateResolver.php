<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Operational registry dates for outgoing payments (carrier / contractor).
 * ponytail: calendar Tue/Thu only — no Russian holiday calendar in v1.
 */
final class OutgoingPaymentRunDateResolver
{
    /**
     * @return list<int>
     */
    public static function weekdays(): array
    {
        $weekdays = config('payment_schedules.outgoing_payment_run_weekdays', [2, 4]);

        return array_values(array_unique(array_map('intval', $weekdays)));
    }

    public static function isOutgoingParty(string $party): bool
    {
        return in_array(strtolower(trim($party)), ['carrier', 'contractor'], true);
    }

    /**
     * Nearest configured payment-run weekday on or after $from (inclusive).
     */
    public static function nextFrom(Carbon $from): ?string
    {
        $weekdays = self::weekdays();
        if ($weekdays === []) {
            return null;
        }

        $cursor = $from->copy()->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            if (in_array($cursor->dayOfWeekIso, $weekdays, true)) {
                return $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return null;
    }

    /**
     * Nearest specific ISO weekday on or after $from (inclusive).
     */
    public static function nextSpecificWeekdayFrom(Carbon $from, int $isoWeekday): ?string
    {
        if ($isoWeekday < 1 || $isoWeekday > 7) {
            return null;
        }

        $cursor = $from->copy()->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            if ($cursor->dayOfWeekIso === $isoWeekday) {
                return $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return null;
    }

    public static function suggestForPlannedDate(?string $plannedDate, ?Carbon $today = null): ?string
    {
        if ($plannedDate === null || trim($plannedDate) === '') {
            return null;
        }

        $today ??= Carbon::today();
        $from = Carbon::parse($plannedDate)->startOfDay();

        if ($from->lt($today)) {
            $from = $today->copy();
        }

        return self::nextFrom($from);
    }
}
