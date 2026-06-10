<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ManagementPayrollHalfCalendar
{
    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     half: int,
     *     period_start: string,
     *     period_end: string,
     *     payment_date: string
     * }
     */
    public static function resolveForDate(CarbonImmutable $date): array
    {
        $day = $date->day;

        if ($day <= 15) {
            $periodStart = $date->startOfMonth();
            $periodEnd = $date->setDay(15);
            $paymentDate = $date->setDay(20);

            return [
                'year' => $date->year,
                'month' => $date->month,
                'half' => 1,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'payment_date' => $paymentDate->toDateString(),
            ];
        }

        $periodStart = $date->setDay(16);
        $periodEnd = $date->endOfMonth();
        $paymentDate = $date->addMonthNoOverflow()->setDay(5);

        return [
            'year' => $date->year,
            'month' => $date->month,
            'half' => 2,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'payment_date' => $paymentDate->toDateString(),
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     half: int,
     *     period_start: string,
     *     period_end: string,
     *     payment_date: string
     * }
     */
    public static function resolveForPaymentDate(CarbonImmutable $paymentDate): array
    {
        if ($paymentDate->day === 20) {
            $anchor = $paymentDate;

            return static::resolveForDate($anchor->setDay(10));
        }

        if ($paymentDate->day === 5) {
            $anchor = $paymentDate->subMonthNoOverflow();

            return static::resolveForDate($anchor->setDay(25));
        }

        return static::resolveForDate($paymentDate);
    }
}
