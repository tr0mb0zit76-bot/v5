<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Даты-якоря для траншей: первая погрузка / последняя выгрузка / дата заказа и т.д.
 *
 * @phpstan-type DateContext array{
 *     first_loading?: ?string,
 *     last_unloading?: ?string,
 *     border_crossing?: ?string,
 *     order_date?: ?string,
 *     loading_date?: ?string,
 *     unloading_date?: ?string,
 * }
 */
final class PaymentInstallmentAnchorDateResolver
{
    /**
     * @param  DateContext  $contextDates
     */
    public static function resolve(?Order $order, string $anchor, array $contextDates = []): ?Carbon
    {
        $anchorNorm = strtolower(trim($anchor));

        $fromContext = match ($anchorNorm) {
            'first_loading' => self::parseDateString($contextDates['first_loading'] ?? null),
            'last_unloading' => self::parseDateString($contextDates['last_unloading'] ?? null),
            'border_crossing' => self::parseDateString($contextDates['border_crossing'] ?? null),
            'order_date' => self::parseDateString($contextDates['order_date'] ?? null),
            'loading_date' => self::parseDateString($contextDates['loading_date'] ?? null),
            'unloading_date' => self::parseDateString($contextDates['unloading_date'] ?? null),
            default => null,
        };

        if ($fromContext !== null) {
            return $fromContext;
        }

        if ($order === null) {
            return null;
        }

        return match ($anchorNorm) {
            'order_date' => self::parseDateString(optional($order->order_date)?->toDateString()),
            'loading_date' => self::parseDateString(optional($order->loading_date)?->toDateString()),
            'unloading_date' => self::parseDateString(optional($order->unloading_date)?->toDateString()),
            'first_loading' => self::firstRoutePointDate($order, 'loading'),
            'last_unloading' => self::lastRoutePointDate($order, 'unloading'),
            'border_crossing' => self::firstRoutePointDate($order, 'border_crossing'),
            default => self::parseDateString(optional($order->loading_date)?->toDateString()),
        };
    }

    private static function parseDateString(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function firstRoutePointDate(Order $order, string $type): ?Carbon
    {
        $order->loadMissing(['legs.routePoints']);

        /** @var Collection<int, mixed> $points */
        $points = $order->legs->sortBy('sequence')->flatMap(fn ($leg) => $leg->routePoints->sortBy('sequence'));

        foreach ($points as $point) {
            if (($point->type ?? null) !== $type) {
                continue;
            }

            $parsed = self::parseRoutePointDate($point);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return self::parseDateString(OrderRouteMilestoneDateResolver::resolveLoadingDate($order));
    }

    private static function lastRoutePointDate(Order $order, string $type): ?Carbon
    {
        $order->loadMissing(['legs.routePoints']);

        $last = null;

        foreach ($order->legs->sortBy('sequence') as $leg) {
            foreach ($leg->routePoints->sortBy('sequence') as $point) {
                if (($point->type ?? null) !== $type) {
                    continue;
                }

                $parsed = self::parseRoutePointDate($point);
                if ($parsed !== null) {
                    $last = $parsed;
                }
            }
        }

        if ($last !== null) {
            return $last;
        }

        return self::parseDateString(OrderRouteMilestoneDateResolver::resolveUnloadingDate($order));
    }

    private static function parseRoutePointDate(mixed $point): ?Carbon
    {
        if (! is_object($point)) {
            return null;
        }

        $actual = $point->actual_date ?? null;
        if ($actual !== null) {
            $parsed = self::parseDateString(is_string($actual) ? $actual : (string) $actual);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        $planned = $point->planned_date ?? null;
        if ($planned === null) {
            return null;
        }

        return self::parseDateString(is_string($planned) ? $planned : (string) $planned);
    }
}
