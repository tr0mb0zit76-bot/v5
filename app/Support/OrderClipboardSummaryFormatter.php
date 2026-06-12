<?php

namespace App\Support;

class OrderClipboardSummaryFormatter
{
    public static function format(
        ?string $loadingCity,
        ?string $unloadingCity,
        ?string $tractorBrand,
        ?string $tractorPlate,
        ?string $trailerBrand,
        ?string $trailerPlate,
        ?string $driverName,
        ?string $driverPassport,
    ): string {
        $parts = [
            'Маршрут: '.self::routeLabel($loadingCity, $unloadingCity),
            'ТС: '.self::vehicleLabel($tractorBrand, $tractorPlate, $trailerBrand, $trailerPlate),
            'Водитель: '.self::driverLabel($driverName, $driverPassport),
        ];

        return implode('; ', $parts);
    }

    private static function routeLabel(?string $loadingCity, ?string $unloadingCity): string
    {
        $from = self::display($loadingCity);
        $to = self::display($unloadingCity);

        return "{$from} — {$to}";
    }

    private static function vehicleLabel(
        ?string $tractorBrand,
        ?string $tractorPlate,
        ?string $trailerBrand,
        ?string $trailerPlate,
    ): string {
        $tractor = self::joinBrandPlate($tractorBrand, $tractorPlate);
        $trailer = self::joinBrandPlate($trailerBrand, $trailerPlate);

        $segments = [];

        if ($tractor !== null) {
            $segments[] = 'тягач '.$tractor;
        }

        if ($trailer !== null) {
            $segments[] = 'прицеп '.$trailer;
        }

        if ($segments === []) {
            return '—';
        }

        return implode(', ', $segments);
    }

    private static function driverLabel(?string $driverName, ?string $driverPassport): string
    {
        $name = self::clean($driverName);
        $passport = self::clean($driverPassport);

        if ($name === null && $passport === null) {
            return '—';
        }

        if ($name === null) {
            return 'паспорт '.$passport;
        }

        if ($passport === null) {
            return $name;
        }

        return $name.', паспорт '.$passport;
    }

    private static function joinBrandPlate(?string $brand, ?string $plate): ?string
    {
        $brandValue = self::clean($brand);
        $plateValue = self::clean($plate);

        if ($brandValue === null && $plateValue === null) {
            return null;
        }

        if ($brandValue === null) {
            return $plateValue;
        }

        if ($plateValue === null) {
            return $brandValue;
        }

        return trim($brandValue.' '.$plateValue);
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
