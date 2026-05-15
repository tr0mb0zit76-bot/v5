<?php

namespace App\Support;

class OrderOneCSummaryFormatter
{
    public static function format(
        ?string $orderNumber,
        ?string $firstLoadingCity,
        ?string $lastUnloadingCity,
        ?string $driverName,
        ?string $vehiclePlates,
    ): string {
        $orderNumber = self::display($orderNumber);
        $route = self::routeLabel($firstLoadingCity, $lastUnloadingCity);
        $driverName = self::display($driverName);
        $vehiclePlates = self::display($vehiclePlates);

        return sprintf(
            'транспортноэкспедиционные услуги по договору заявке (%s), маршрут (%s), водитель (%s), т/с (%s)',
            $orderNumber,
            $route,
            $driverName,
            $vehiclePlates,
        );
    }

    public static function vehiclePlatesLabel(?string $tractorPlate, ?string $trailerPlate): ?string
    {
        $tractor = self::clean($tractorPlate);
        $trailer = self::clean($trailerPlate);

        if ($tractor !== null && $trailer !== null) {
            return "{$tractor} / {$trailer}";
        }

        return $tractor ?? $trailer;
    }

    private static function routeLabel(?string $firstLoading, ?string $lastUnloading): string
    {
        $from = self::display($firstLoading);
        $to = self::display($lastUnloading);

        return "{$from} - {$to}";
    }

    private static function display(?string $value): string
    {
        return self::clean($value) ?? '—';
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
