<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\OrderLeg;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Фактические даты погрузки/выгрузки по маршруту заказа.
 */
final class RoutePointActualMilestones
{
    /**
     * @return array{actual_loading: ?CarbonInterface, actual_unloading: ?CarbonInterface}
     */
    public static function forOrder(Order $order): array
    {
        $performers = is_array($order->performers) ? $order->performers : [];
        $fromPerformers = PerformerRouteActualDates::milestonesFromPerformers($performers);

        if (! $order->relationLoaded('legs')) {
            $order->loadMissing([
                'legs' => fn ($q) => $q->orderBy('sequence'),
                'legs.routePoints' => fn ($q) => $q->orderBy('sequence'),
            ]);
        }

        $fromRoute = self::fromLegsCollection(
            $order->legs,
            $order->loading_date,
            $order->unloading_date,
        );

        return [
            'actual_loading' => $fromPerformers['actual_loading'] ?? $fromRoute['actual_loading'],
            'actual_unloading' => $fromPerformers['actual_unloading'] ?? $fromRoute['actual_unloading'],
        ];
    }

    /**
     * @param  Collection<int, OrderLeg>  $legs
     * @return array{actual_loading: ?CarbonInterface, actual_unloading: ?CarbonInterface}
     */
    public static function fromLegsCollection(
        Collection $legs,
        ?CarbonInterface $fallbackLoading = null,
        ?CarbonInterface $fallbackUnloading = null,
    ): array {
        $hasRoutePoints = $legs->contains(
            fn ($leg): bool => $leg->routePoints->isNotEmpty()
        );

        if (! $hasRoutePoints) {
            return [
                'actual_loading' => $fallbackLoading,
                'actual_unloading' => $fallbackUnloading,
            ];
        }

        $firstActualLoading = null;

        foreach ($legs as $leg) {
            foreach ($leg->routePoints as $point) {
                if ($point->type === 'loading' && $point->actual_date !== null) {
                    $firstActualLoading = $point->actual_date;

                    break 2;
                }
            }
        }

        $lastUnloadingPoint = null;

        foreach ($legs as $leg) {
            foreach ($leg->routePoints as $point) {
                if ($point->type === 'unloading') {
                    $lastUnloadingPoint = $point;
                }
            }
        }

        $lastActualUnloading = $lastUnloadingPoint?->actual_date;

        return [
            'actual_loading' => $firstActualLoading,
            'actual_unloading' => $lastActualUnloading,
        ];
    }
}
