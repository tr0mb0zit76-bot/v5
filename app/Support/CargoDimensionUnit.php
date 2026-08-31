<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Единицы габаритов груза: значение как введено + канон в метрах (как weight_value / weight).
 */
final class CargoDimensionUnit
{
    public const METER = 'm';

    public const CENTIMETER = 'cm';

    public const MILLIMETER = 'mm';

    /** @var list<string> */
    public const UNITS = [self::METER, self::CENTIMETER, self::MILLIMETER];

    public static function normalize(?string $unit): string
    {
        $normalized = strtolower(trim((string) $unit));

        return in_array($normalized, self::UNITS, true) ? $normalized : self::METER;
    }

    public static function toMeters(?float $value, ?string $unit): ?float
    {
        if ($value === null) {
            return null;
        }

        return match (self::normalize($unit)) {
            self::CENTIMETER => $value / 100,
            self::MILLIMETER => $value / 1000,
            default => $value,
        };
    }

    /**
     * @return array{length: ?float, width: ?float, height: ?float, unit: string, length_m: ?float, width_m: ?float, height_m: ?float}
     */
    public static function resolveFromPayload(array $item): array
    {
        $hasExplicitValues = array_key_exists('length_value', $item)
            || array_key_exists('width_value', $item)
            || array_key_exists('height_value', $item);

        if ($hasExplicitValues) {
            $unit = self::normalize(isset($item['dimension_unit']) ? (string) $item['dimension_unit'] : null);
            $lengthValue = self::nullableFloat($item['length_value'] ?? null);
            $widthValue = self::nullableFloat($item['width_value'] ?? null);
            $heightValue = self::nullableFloat($item['height_value'] ?? null);
        } else {
            // Легаси: length_m / width_m / height_m уже в метрах.
            $unit = self::METER;
            $lengthValue = self::nullableFloat($item['length_m'] ?? null);
            $widthValue = self::nullableFloat($item['width_m'] ?? null);
            $heightValue = self::nullableFloat($item['height_m'] ?? null);
        }

        return [
            'length' => $lengthValue,
            'width' => $widthValue,
            'height' => $heightValue,
            'unit' => $unit,
            'length_m' => self::toMeters($lengthValue, $unit),
            'width_m' => self::toMeters($widthValue, $unit),
            'height_m' => self::toMeters($heightValue, $unit),
        ];
    }

    public static function volumeM3(?float $lengthM, ?float $widthM, ?float $heightM): ?float
    {
        if ($lengthM === null || $widthM === null || $heightM === null) {
            return null;
        }

        if ($lengthM <= 0 || $widthM <= 0 || $heightM <= 0) {
            return null;
        }

        return round($lengthM * $widthM * $heightM, 3);
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
