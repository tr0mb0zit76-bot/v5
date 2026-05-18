<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RoutePoint;

/**
 * Слияние normalized_data из колонки и metadata + сохранение city для отображения в гриде.
 */
final class RoutePointNormalizedData
{
    /**
     * @return array<string, mixed>
     */
    public static function resolveForWizard(RoutePoint $point): array
    {
        $fromMetadata = data_get($point->metadata, 'normalized_data', []);
        if (! is_array($fromMetadata)) {
            $fromMetadata = [];
        }

        $fromColumn = $point->normalized_data;
        if (! is_array($fromColumn)) {
            $fromColumn = [];
        }

        return self::prepareForStorage(array_merge($fromMetadata, $fromColumn));
    }

    /**
     * @param  array<string, mixed>  $normalizedData
     * @return array<string, mixed>
     */
    public static function prepareForStorage(array $normalizedData, ?string $address = null): array
    {
        $city = self::nonEmptyString($normalizedData['city'] ?? null);

        if ($city === null) {
            $city = self::nonEmptyString($normalizedData['settlement'] ?? null)
                ?? self::nonEmptyString($normalizedData['city_with_type'] ?? null);
            if ($city !== null) {
                $normalizedData['city'] = $city;
            }
        }

        if (self::nonEmptyString($normalizedData['city'] ?? null) === null && $address !== null) {
            $extracted = self::extractCityFromAddress($address);
            if ($extracted !== null) {
                $normalizedData['city'] = $extracted;
            }
        }

        return $normalizedData;
    }

    public static function extractCityFromAddress(string $address): ?string
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $firstSegment = trim(explode(',', $address, 2)[0] ?? '');
        if ($firstSegment === '') {
            return null;
        }

        $firstSegment = preg_replace('/^(г\.?|город)\s+/iu', '', $firstSegment) ?? $firstSegment;

        return self::nonEmptyString($firstSegment);
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
