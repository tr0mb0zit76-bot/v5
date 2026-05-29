<?php

namespace App\Support;

final class CargoPerformerAllocationBuilder
{
    /**
     * @param  list<array<string, mixed>>  $performers
     */
    public static function needsPerformerAllocation(array $performers): bool
    {
        if ($performers === []) {
            return false;
        }

        if (count($performers) > 1) {
            return true;
        }

        return ($performers[0]['carrier_mode'] ?? 'single') === 'split';
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array{stage: string, carrier_slot: int|null}>
     */
    public static function columnsFromPerformers(array $performers): array
    {
        $columns = [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($performer['stage'] ?? ''));
            $mode = ($performer['carrier_mode'] ?? 'single') === 'split' ? 'split' : 'single';

            if ($mode === 'split' && is_array($performer['split_carriers'] ?? null) && $performer['split_carriers'] !== []) {
                foreach ($performer['split_carriers'] as $index => $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $slotNumber = (int) ($slot['slot'] ?? ($index + 1));
                    $columns[] = [
                        'stage' => $stage,
                        'carrier_slot' => $slotNumber > 0 ? $slotNumber : null,
                    ];
                }

                continue;
            }

            $columns[] = [
                'stage' => $stage,
                'carrier_slot' => null,
            ];
        }

        return $columns;
    }

    /**
     * Берёт allocations из запроса или строит дубликаты строки заказчика по «простым» колонкам исполнителей.
     *
     * @param  array<string, mixed>  $cargoItem
     * @param  list<array<string, mixed>>  $performers
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    public static function resolveForCargoItem(array $cargoItem, array $performers): array
    {
        if (! self::needsPerformerAllocation($performers)) {
            return [];
        }

        $existing = self::extractNormalizedAllocations($cargoItem);
        if ($existing !== []) {
            return $existing;
        }

        return self::buildDefaultDuplicates($cargoItem, $performers);
    }

    /**
     * @param  array<string, mixed>  $cargoItem
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    private static function extractNormalizedAllocations(array $cargoItem): array
    {
        $raw = $cargoItem['performer_allocations'] ?? null;
        if (! is_array($raw)) {
            $atiPayload = $cargoItem['ati_cargo_payload'] ?? null;
            $raw = is_array($atiPayload) && ! array_is_list($atiPayload)
                ? ($atiPayload['performer_allocations'] ?? null)
                : null;
        }

        if (! is_array($raw)) {
            return [];
        }

        return CargoPerformerAllocationNormalizer::normalizeForStorage($raw);
    }

    /**
     * @param  array<string, mixed>  $cargoItem
     * @param  list<array<string, mixed>>  $performers
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    private static function buildDefaultDuplicates(array $cargoItem, array $performers): array
    {
        $columns = self::columnsFromPerformers($performers);
        if ($columns === []) {
            return [];
        }

        $packageCount = self::normalizeNullableFloat($cargoItem['package_count'] ?? null);
        if ($packageCount === null || $packageCount <= 0) {
            return [];
        }

        $perPlaceWeight = self::normalizeNullableFloat($cargoItem['weight_value'] ?? $cargoItem['weight_kg'] ?? null);
        $weightUnit = ($cargoItem['weight_unit'] ?? 'kg') === 't' ? 't' : 'kg';
        $allocationWeight = self::allocationWeightForPackages($packageCount, $perPlaceWeight, $weightUnit);

        $columnsByStage = [];
        foreach ($columns as $column) {
            $columnsByStage[$column['stage']][] = $column;
        }

        $result = [];

        foreach ($columns as $column) {
            $stageColumns = $columnsByStage[$column['stage']] ?? [];

            if (count($stageColumns) > 1) {
                continue;
            }

            $result[] = [
                'stage' => $column['stage'],
                'carrier_slot' => $column['carrier_slot'],
                'package_count' => $packageCount,
                'weight_value' => $allocationWeight,
            ];
        }

        return CargoPerformerAllocationNormalizer::normalizeForStorage($result);
    }

    private static function allocationWeightForPackages(float $packageCount, ?float $perPlaceWeight, string $weightUnit): ?float
    {
        if ($perPlaceWeight === null || $perPlaceWeight <= 0) {
            return null;
        }

        return round($perPlaceWeight * $packageCount, $weightUnit === 't' ? 3 : 2);
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
