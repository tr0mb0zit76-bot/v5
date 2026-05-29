<?php

namespace App\Support;

final class CargoPerformerAllocationNormalizer
{
    public static function normalizeStageIdentifier(?string $stage): string
    {
        $value = trim((string) $stage);

        if ($value === '') {
            return 'leg_1';
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^leg_(\d+)$/i', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $allocations
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    public static function normalizeForStorage(array $allocations): array
    {
        $normalized = [];

        foreach ($allocations as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stage = self::normalizeStageIdentifier((string) ($row['stage'] ?? ''));
            $carrierSlotRaw = $row['carrier_slot'] ?? null;
            $carrierSlot = $carrierSlotRaw === null || $carrierSlotRaw === ''
                ? null
                : (int) $carrierSlotRaw;
            $packageCount = self::normalizeNullableFloat($row['package_count'] ?? null);
            $weightValue = self::normalizeNullableFloat($row['weight_value'] ?? null);

            if ($packageCount === null && $weightValue === null) {
                continue;
            }

            $normalized[] = [
                'stage' => $stage,
                'carrier_slot' => $carrierSlot !== null && $carrierSlot > 0 ? $carrierSlot : null,
                'package_count' => $packageCount,
                'weight_value' => $weightValue,
            ];
        }

        return $normalized;
    }

    private static function normalizeNullableFloat(mixed $value): ?float
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
