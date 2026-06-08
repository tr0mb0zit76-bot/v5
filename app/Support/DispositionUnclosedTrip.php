<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Рейс «не закрыт» для диспозиции: перевозка ещё не завершена фактической выгрузкой.
 */
final class DispositionUnclosedTrip
{
    public static function isUnclosed(Order $order, ?string $today = null): bool
    {
        if (! DispositionInTransitResolver::isInTransit($order)) {
            return false;
        }

        return self::isUnclosedBySchedule($order, $today);
    }

    public static function isUnclosedBySchedule(Order $order, ?string $today = null): bool
    {
        $todayString = $today ?? Carbon::today()->toDateString();
        $unloading = $order->unloading_date?->toDateString();

        if ($unloading === null) {
            return true;
        }

        return $unloading >= $todayString;
    }

    /**
     * @param  iterable<int, Order>  $orders
     */
    public static function firstUnclosedLoadingDate(iterable $orders, ?string $today = null): ?string
    {
        $earliest = null;

        foreach ($orders as $order) {
            if (! self::isUnclosed($order, $today)) {
                continue;
            }

            $loading = $order->loading_date?->toDateString();

            if ($loading === null) {
                continue;
            }

            $earliest = $earliest === null || $loading < $earliest ? $loading : $earliest;
        }

        return $earliest;
    }

    /**
     * Колонка для начального горизонтального скролла: «сегодня» у левого края,
     * если дата внутри шкалы, иначе ближайший край диапазона.
     *
     * @param  list<string>  $dates  Y-m-d по возрастанию
     */
    public static function resolveScrollAnchorDate(array $dates, string $today): string
    {
        if ($dates === []) {
            return $today;
        }

        $min = $dates[0];
        $max = $dates[array_key_last($dates)];

        if ($today < $min) {
            return $min;
        }

        if ($today > $max) {
            return $max;
        }

        return $today;
    }
}
