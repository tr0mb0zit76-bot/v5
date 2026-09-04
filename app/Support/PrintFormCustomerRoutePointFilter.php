<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Для печати заказчику на многоплечевом маршруте скрывает стыки плеч
 * (выгрузка legᵢ + погрузка legᵢ₊₁) — точки перегрузки под смену перевозчика.
 */
final class PrintFormCustomerRoutePointFilter
{
    public static function shouldApply(?OrderPrintFormContext $context): bool
    {
        if ($context === null) {
            return false;
        }

        if ($context->printParty !== 'customer') {
            return false;
        }

        if ($context->routeLegsAsTableRows) {
            return false;
        }

        if ($context->legStage !== null && $context->legStage !== '') {
            return false;
        }

        if ($context->carrierContractorId !== null) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, mixed>  $routePoints
     * @return Collection<int, mixed>
     */
    public static function filter(Order $order, Collection $routePoints): Collection
    {
        if ($routePoints->isEmpty() || ! $order->relationLoaded('legs')) {
            return $routePoints;
        }

        $legs = $order->legs->sortBy(fn ($leg): int => (int) ($leg->sequence ?? 0))->values();
        if ($legs->count() < 2) {
            return $routePoints;
        }

        $hubKeys = [];

        for ($index = 0; $index < $legs->count() - 1; $index++) {
            $currentLegId = (int) ($legs[$index]->id ?? 0);
            $nextLegId = (int) ($legs[$index + 1]->id ?? 0);
            if ($currentLegId <= 0 || $nextLegId <= 0) {
                continue;
            }

            $currentLegPoints = $routePoints
                ->filter(fn (mixed $point): bool => (int) data_get($point, 'order_leg_id') === $currentLegId)
                ->values();
            $nextLegPoints = $routePoints
                ->filter(fn (mixed $point): bool => (int) data_get($point, 'order_leg_id') === $nextLegId)
                ->values();

            $lastUnload = $currentLegPoints
                ->filter(fn (mixed $point): bool => strtolower(trim((string) data_get($point, 'type'))) === 'unloading')
                ->sortByDesc(fn (mixed $point): int => (int) data_get($point, 'sequence'))
                ->first();

            $firstLoad = $nextLegPoints
                ->filter(fn (mixed $point): bool => strtolower(trim((string) data_get($point, 'type'))) === 'loading')
                ->sortBy(fn (mixed $point): int => (int) data_get($point, 'sequence'))
                ->first();

            if ($lastUnload !== null) {
                $hubKeys[self::pointKey($lastUnload)] = true;
            }

            if ($firstLoad !== null) {
                $hubKeys[self::pointKey($firstLoad)] = true;
            }
        }

        if ($hubKeys === []) {
            return $routePoints;
        }

        return $routePoints
            ->reject(fn (mixed $point): bool => isset($hubKeys[self::pointKey($point)]))
            ->values();
    }

    private static function pointKey(mixed $point): string
    {
        $id = (int) data_get($point, 'id');
        if ($id > 0) {
            return 'id:'.$id;
        }

        return 'obj:'.spl_object_id($point);
    }
}
