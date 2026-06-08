<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Плановая дата одной транши от якоря и смещения.
 */
final class PaymentInstallmentPlanner
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ?string>  $contextDates
     */
    public static function plannedDateForInstallment(array $row, ?Order $order, array $contextDates = []): ?string
    {
        $anchor = (string) ($row['anchor'] ?? 'first_loading');
        $base = PaymentInstallmentAnchorDateResolver::resolve($order, $anchor, $contextDates);
        if ($base === null) {
            return null;
        }

        $shifted = CalendarBankDayShifter::shift(
            $base,
            (int) ($row['offset_days'] ?? 0),
            (string) ($row['offset_unit'] ?? CalendarBankDayShifter::UNIT_CALENDAR),
        );

        return $shifted->toDateString();
    }

    /**
     * @param  list<array<string, mixed>>  $installments
     * @param  array<string, ?string>  $contextDates
     * @return list<?string>
     */
    public static function plannedDatesForInstallments(array $installments, ?Order $order, array $contextDates = []): array
    {
        $out = [];
        foreach ($installments as $row) {
            if (! is_array($row)) {
                $out[] = null;

                continue;
            }
            $out[] = self::plannedDateForInstallment($row, $order, $contextDates);
        }

        return $out;
    }

    /**
     * @param  array<string, ?string>  $contextDates
     */
    public static function dateContextFromOrder(Order $order): array
    {
        $order->loadMissing(['legs.routePoints']);

        $firstLoading = PaymentInstallmentAnchorDateResolver::resolve($order, 'first_loading', []);
        $lastUnloading = PaymentInstallmentAnchorDateResolver::resolve($order, 'last_unloading', []);

        $borderCrossing = PaymentInstallmentAnchorDateResolver::resolve($order, 'border_crossing', []);

        return [
            'first_loading' => $firstLoading?->toDateString(),
            'last_unloading' => $lastUnloading?->toDateString(),
            'border_crossing' => $borderCrossing?->toDateString(),
            'order_date' => optional($order->order_date)?->toDateString(),
            'loading_date' => OrderRouteMilestoneDateResolver::resolveLoadingDate($order),
            'unloading_date' => OrderRouteMilestoneDateResolver::resolveUnloadingDate($order),
        ];
    }

    /**
     * @param  array<string, ?string>  $contextDates
     */
    public static function dateContextFromWizardPayload(array $validated): array
    {
        $routePoints = collect($validated['route_points'] ?? [])->sortBy('sequence')->values();
        $firstLoading = $routePoints->firstWhere('type', 'loading');
        $lastUnloading = $routePoints->where('type', 'unloading')->last();
        $firstBorder = $routePoints->firstWhere('type', 'border_crossing');

        $firstDate = self::routePointDateString(is_array($firstLoading) ? $firstLoading : null);
        $lastDate = self::routePointDateString(is_array($lastUnloading) ? $lastUnloading : null);
        $borderDate = self::routePointDateString(is_array($firstBorder) ? $firstBorder : null);
        $orderDate = isset($validated['order_date']) ? (string) $validated['order_date'] : null;

        return [
            'first_loading' => $firstDate,
            'last_unloading' => $lastDate,
            'border_crossing' => $borderDate,
            'order_date' => $orderDate,
            'loading_date' => $firstDate,
            'unloading_date' => $lastDate,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $point
     */
    private static function routePointDateString(?array $point): ?string
    {
        if ($point === null) {
            return null;
        }

        $raw = $point['planned_date'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
