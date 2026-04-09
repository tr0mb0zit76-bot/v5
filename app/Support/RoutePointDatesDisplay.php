<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Даты погрузки/выгрузки для грида: первая точка загрузки / последняя выгрузка по порядку плечей и sequence.
 */
final class RoutePointDatesDisplay
{
    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, array{
     *   loading_display: ?string,
     *   unloading_display: ?string,
     *   loading_kind: 'planned'|'actual'|'none',
     *   unloading_kind: 'planned'|'actual'|'none',
     * }>
     */
    public static function mapForOrderIds(array $orderIds): Collection
    {
        if ($orderIds === [] || ! Schema::hasTable('route_points')) {
            return collect();
        }

        if (! Schema::hasColumn('route_points', 'planned_date') && ! Schema::hasColumn('route_points', 'actual_date')) {
            return collect();
        }

        $select = ['order_legs.order_id', 'route_points.type'];
        if (Schema::hasColumn('route_points', 'planned_date')) {
            $select[] = 'route_points.planned_date';
        }
        if (Schema::hasColumn('route_points', 'actual_date')) {
            $select[] = 'route_points.actual_date';
        }

        $points = DB::table('route_points')
            ->join('order_legs', 'order_legs.id', '=', 'route_points.order_leg_id')
            ->whereIn('order_legs.order_id', $orderIds)
            ->orderBy('order_legs.sequence')
            ->orderBy('route_points.sequence')
            ->get($select);

        $byOrder = $points->groupBy('order_id');

        return collect($orderIds)->mapWithKeys(function (int $orderId) use ($byOrder): array {
            $rows = $byOrder->get($orderId, collect());
            $loading = $rows->where('type', 'loading')->first();
            $unloading = $rows->where('type', 'unloading')->last();

            return [
                $orderId => [
                    'loading_display' => self::displayDate($loading),
                    'unloading_display' => self::displayDate($unloading),
                    'loading_kind' => self::kind($loading),
                    'unloading_kind' => self::kind($unloading),
                ],
            ];
        });
    }

    private static function displayDate(?object $point): ?string
    {
        if ($point === null) {
            return null;
        }

        $actual = property_exists($point, 'actual_date') ? $point->actual_date : null;
        if (filled($actual)) {
            return self::normalizeDateString($actual);
        }

        $planned = property_exists($point, 'planned_date') ? $point->planned_date : null;
        if (filled($planned)) {
            return self::normalizeDateString($planned);
        }

        return null;
    }

    /**
     * @return 'planned'|'actual'|'none'
     */
    private static function kind(?object $point): string
    {
        if ($point === null) {
            return 'none';
        }

        $actual = property_exists($point, 'actual_date') ? $point->actual_date : null;
        if (filled($actual)) {
            return 'actual';
        }

        $planned = property_exists($point, 'planned_date') ? $point->planned_date : null;
        if (filled($planned)) {
            return 'planned';
        }

        return 'none';
    }

    private static function normalizeDateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $s = (string) $value;

        return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
    }
}
