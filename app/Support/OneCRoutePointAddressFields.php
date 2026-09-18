<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RoutePoint;

/**
 * Поля адреса точки маршрута для выгрузки в 1С (ЭПД / fill-титул).
 * Полная строка остаётся в address; составные части — отдельно.
 */
final class OneCRoutePointAddressFields
{
    /**
     * @param  RoutePoint|object|array<string, mixed>  $point
     * @return array{
     *     address: ?string,
     *     region: ?string,
     *     city: ?string,
     *     street: ?string,
     *     house: ?string,
     *     flat: ?string,
     *     postal_code: ?string
     * }
     */
    public static function fromRoutePoint(object|array $point): array
    {
        $address = self::nonEmptyString(self::attr($point, 'address'));
        $normalized = self::normalizedData($point, $address);

        return [
            'address' => $address,
            'region' => self::nonEmptyString($normalized['region'] ?? null),
            'city' => self::nonEmptyString($normalized['city'] ?? null),
            'street' => self::nonEmptyString($normalized['street'] ?? null),
            'house' => self::nonEmptyString($normalized['house'] ?? null),
            'flat' => self::nonEmptyString($normalized['flat'] ?? $normalized['apartment'] ?? null),
            'postal_code' => self::nonEmptyString($normalized['postal_code'] ?? null),
        ];
    }

    /**
     * @param  RoutePoint|object|array<string, mixed>  $point
     * @return array<string, mixed>
     */
    private static function normalizedData(object|array $point, ?string $address): array
    {
        if ($point instanceof RoutePoint) {
            return RoutePointNormalizedData::resolveForWizard($point);
        }

        $raw = self::attr($point, 'normalized_data');
        if (! is_array($raw)) {
            $raw = [];
        }

        return RoutePointNormalizedData::prepareForStorage($raw, $address);
    }

    /**
     * @param  RoutePoint|object|array<string, mixed>  $point
     */
    private static function attr(object|array $point, string $key): mixed
    {
        if (is_array($point)) {
            return $point[$key] ?? null;
        }

        return $point->{$key} ?? null;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
