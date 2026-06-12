<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class ManagementAccountingPeriodSupport
{
    /**
     * @param  list<array{key: string, start: string, end: string}>  $columns
     */
    public static function columnKeyForDate(array $columns, string $date): ?string
    {
        $normalized = self::normalizeDateString($date);

        if ($normalized === null) {
            return null;
        }

        foreach ($columns as $column) {
            if ($normalized >= $column['start'] && $normalized <= $column['end']) {
                return $column['key'];
            }
        }

        return null;
    }

    public static function normalizeDateString(string $date): ?string
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $matches) === 1) {
            return $matches[1];
        }

        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function pivotMonthLabel(CarbonImmutable $month): string
    {
        static $labels = [
            1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр',
            5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'авг',
            9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек',
        ];

        return $labels[(int) $month->month] ?? (string) $month->month;
    }
}
