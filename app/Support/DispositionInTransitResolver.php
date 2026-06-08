<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * «В пути» для диспозиции: между фактической погрузкой и фактической выгрузкой.
 */
final class DispositionInTransitResolver
{
    public static function isInTransit(Order $order): bool
    {
        $milestones = self::milestones($order);

        return $milestones['actual_loading'] !== null
            && $milestones['actual_unloading'] === null;
    }

    /**
     * @return array{actual_loading: ?CarbonInterface, actual_unloading: ?CarbonInterface}
     */
    public static function milestones(Order $order): array
    {
        $performers = is_array($order->performers) ? $order->performers : [];
        $fromPerformers = PerformerRouteActualDates::milestonesFromPerformers($performers);

        if (! $order->relationLoaded('legs')) {
            $order->loadMissing([
                'legs' => fn ($query) => $query->orderBy('sequence'),
                'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
            ]);
        }

        $fromRoute = RoutePointActualMilestones::fromLegsCollection(
            $order->legs,
            null,
            null,
        );

        return [
            'actual_loading' => $fromPerformers['actual_loading'] ?? $fromRoute['actual_loading'],
            'actual_unloading' => $fromPerformers['actual_unloading'] ?? $fromRoute['actual_unloading'],
        ];
    }
}
