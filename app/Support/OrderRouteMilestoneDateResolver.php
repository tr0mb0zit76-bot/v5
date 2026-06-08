<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\RoutePoint;

/**
 * Единая дата погрузки/выгрузки для графика оплат: факт точки маршрута → план → performers → колонка заказа.
 */
final class OrderRouteMilestoneDateResolver
{
    public static function resolveLoadingDate(Order $order): ?string
    {
        return self::resolveEndpointDate($order, 'loading', 'first');
    }

    public static function resolveUnloadingDate(Order $order): ?string
    {
        return self::resolveEndpointDate($order, 'unloading', 'last');
    }

    public static function syncToOrder(Order $order): Order
    {
        $loading = self::resolveLoadingDate($order);
        $unloading = self::resolveUnloadingDate($order);

        $fill = [];
        if ($loading !== null) {
            $fill['loading_date'] = $loading;
        }
        if ($unloading !== null) {
            $fill['unloading_date'] = $unloading;
        }

        if ($fill !== []) {
            $order->forceFill($fill)->save();
        }

        return $order->fresh() ?? $order;
    }

    private static function resolveEndpointDate(Order $order, string $type, string $which): ?string
    {
        $order->loadMissing([
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        $candidate = null;

        foreach ($order->legs as $leg) {
            foreach ($leg->routePoints as $point) {
                if ($point->type !== $type) {
                    continue;
                }

                $date = self::pointDateString($point);
                if ($date === null) {
                    continue;
                }

                if ($which === 'first') {
                    return $date;
                }

                $candidate = $date;
            }
        }

        if ($candidate !== null) {
            return $candidate;
        }

        $performers = is_array($order->performers) ? $order->performers : [];
        $milestones = PerformerRouteActualDates::milestonesFromPerformers($performers);
        $fromPerformers = $which === 'first'
            ? $milestones['actual_loading']?->toDateString()
            : $milestones['actual_unloading']?->toDateString();

        if ($fromPerformers !== null) {
            return $fromPerformers;
        }

        $column = $which === 'first' ? $order->loading_date : $order->unloading_date;

        return $column?->toDateString();
    }

    private static function pointDateString(RoutePoint $point): ?string
    {
        if ($point->actual_date !== null) {
            return $point->actual_date->toDateString();
        }

        if ($point->planned_date !== null) {
            return $point->planned_date->toDateString();
        }

        return null;
    }
}
