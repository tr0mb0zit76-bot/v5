<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LeadCargoItem;

final class LeadCargoItemPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function toDatabase(array $item): array
    {
        $weightValue = self::normalizeFloat($item['weight_value'] ?? $item['weight_kg'] ?? null);
        $weightUnit = ($item['weight_unit'] ?? 'kg') === 't' ? 't' : 'kg';
        $weightKg = $weightValue;
        if ($weightKg !== null && $weightUnit === 't') {
            $weightKg *= 1000;
        }

        $length = self::normalizeFloat($item['length_m'] ?? null);
        $width = self::normalizeFloat($item['width_m'] ?? null);
        $height = self::normalizeFloat($item['height_m'] ?? null);
        $volume = $length !== null && $width !== null && $height !== null && $length > 0 && $width > 0 && $height > 0
            ? round($length * $width * $height, 3)
            : self::normalizeFloat($item['volume_m3'] ?? null);

        $cargoType = self::resolveCargoType($item);

        $metadata = array_filter([
            'weight_value' => $weightValue,
            'weight_unit' => $weightUnit,
            'length_m' => $length,
            'width_m' => $width,
            'height_m' => $height,
            'diameter_m' => self::normalizeFloat($item['diameter_m'] ?? null),
            'cargo_type_id' => self::normalizeInt($item['cargo_type_id'] ?? null),
            'cargo_type_label' => self::nullIfEmpty($item['cargo_type_label'] ?? null),
            'pack_type_id' => self::normalizeInt($item['pack_type_id'] ?? null),
            'pack_type_label' => self::nullIfEmpty($item['pack_type_label'] ?? null),
            'loading_type_id' => self::normalizeInt($item['loading_type_id'] ?? null),
            'loading_type_code' => self::nullIfEmpty($item['loading_type_code'] ?? null),
            'loading_type_label' => self::nullIfEmpty($item['loading_type_label'] ?? null),
            'loading_type_items' => self::normalizeDictionaryItems($item['loading_type_items'] ?? null),
            'loading_type_ids' => self::normalizeIdList($item['loading_type_ids'] ?? null),
            'truck_body_type_id' => self::normalizeInt($item['truck_body_type_id'] ?? null),
            'truck_body_type_code' => self::nullIfEmpty($item['truck_body_type_code'] ?? null),
            'truck_body_type_label' => self::nullIfEmpty($item['truck_body_type_label'] ?? null),
            'truck_body_type_items' => self::normalizeDictionaryItems($item['truck_body_type_items'] ?? null),
            'truck_body_type_ids' => self::normalizeIdList($item['truck_body_type_ids'] ?? null),
            'trailer_type_id' => self::normalizeInt($item['trailer_type_id'] ?? null),
            'trailer_type_code' => self::nullIfEmpty($item['trailer_type_code'] ?? null),
            'trailer_type_label' => self::nullIfEmpty($item['trailer_type_label'] ?? null),
            'trailer_type_items' => self::normalizeDictionaryItems($item['trailer_type_items'] ?? null),
            'trailer_type_ids' => self::normalizeIdList($item['trailer_type_ids'] ?? null),
            'is_oversized' => (bool) ($item['is_oversized'] ?? $cargoType === 'oversized'),
            'is_fragile' => (bool) ($item['is_fragile'] ?? $cargoType === 'fragile'),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return [
            'name' => trim((string) ($item['name'] ?? '')),
            'description' => self::nullIfEmpty($item['description'] ?? null),
            'weight_kg' => $weightKg,
            'volume_m3' => $volume,
            'package_type' => self::nullIfEmpty($item['package_type'] ?? null),
            'package_count' => self::normalizeInt($item['package_count'] ?? null),
            'dangerous_goods' => (bool) ($item['dangerous_goods'] ?? $cargoType === 'dangerous'),
            'dangerous_class' => self::nullIfEmpty($item['dangerous_class'] ?? null),
            'hs_code' => self::nullIfEmpty($item['hs_code'] ?? null),
            'cargo_type' => $cargoType,
            'metadata' => $metadata === [] ? null : $metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toFrontend(LeadCargoItem $cargo): array
    {
        $metadata = is_array($cargo->metadata) ? $cargo->metadata : [];

        return [
            'id' => $cargo->id,
            'name' => $cargo->name ?? '',
            'description' => $cargo->description ?? '',
            'weight_value' => $metadata['weight_value'] ?? $cargo->weight_kg,
            'weight_kg' => $metadata['weight_value'] ?? $cargo->weight_kg,
            'weight_unit' => $metadata['weight_unit'] ?? 'kg',
            'volume_m3' => $cargo->volume_m3,
            'length_m' => $metadata['length_m'] ?? null,
            'width_m' => $metadata['width_m'] ?? null,
            'height_m' => $metadata['height_m'] ?? null,
            'diameter_m' => $metadata['diameter_m'] ?? null,
            'pack_type_id' => $metadata['pack_type_id'] ?? null,
            'pack_type_label' => $metadata['pack_type_label'] ?? '',
            'package_type' => $cargo->package_type,
            'loading_type_id' => $metadata['loading_type_id'] ?? null,
            'loading_type_code' => $metadata['loading_type_code'] ?? null,
            'loading_type_label' => $metadata['loading_type_label'] ?? '',
            'loading_type_items' => $metadata['loading_type_items'] ?? [],
            'loading_type_ids' => $metadata['loading_type_ids'] ?? [],
            'truck_body_type_id' => $metadata['truck_body_type_id'] ?? null,
            'truck_body_type_code' => $metadata['truck_body_type_code'] ?? null,
            'truck_body_type_label' => $metadata['truck_body_type_label'] ?? '',
            'truck_body_type_items' => $metadata['truck_body_type_items'] ?? [],
            'truck_body_type_ids' => $metadata['truck_body_type_ids'] ?? [],
            'trailer_type_id' => $metadata['trailer_type_id'] ?? null,
            'trailer_type_code' => $metadata['trailer_type_code'] ?? null,
            'trailer_type_label' => $metadata['trailer_type_label'] ?? '',
            'trailer_type_items' => $metadata['trailer_type_items'] ?? [],
            'trailer_type_ids' => $metadata['trailer_type_ids'] ?? [],
            'package_count' => $cargo->package_count,
            'dangerous_goods' => (bool) $cargo->dangerous_goods,
            'dangerous_class' => $cargo->dangerous_class ?? '',
            'hs_code' => $cargo->hs_code ?? '',
            'cargo_type_id' => $metadata['cargo_type_id'] ?? null,
            'cargo_type_label' => $metadata['cargo_type_label'] ?? '',
            'cargo_type' => $cargo->cargo_type ?? 'general',
            'is_oversized' => (bool) ($metadata['is_oversized'] ?? $cargo->cargo_type === 'oversized'),
            'is_fragile' => (bool) ($metadata['is_fragile'] ?? $cargo->cargo_type === 'fragile'),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function isMeaningful(array $item): bool
    {
        return trim((string) ($item['name'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function resolveCargoType(array $item): string
    {
        $cargoType = self::nullIfEmpty($item['cargo_type'] ?? null);
        if ($cargoType !== null) {
            return $cargoType;
        }

        if ((bool) ($item['dangerous_goods'] ?? false)) {
            return 'dangerous';
        }

        return 'general';
    }

    /**
     * @return list<array{id:int|null, code:string|null, label:string}>
     */
    private static function normalizeDictionaryItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => self::normalizeInt($item['id'] ?? null),
                'code' => self::nullIfEmpty($item['code'] ?? null),
                'label' => self::nullIfEmpty($item['label'] ?? null) ?? '',
            ];
        }

        return $normalized;
    }

    /**
     * @return list<int>
     */
    private static function normalizeIdList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $id): ?int => self::normalizeInt($id),
            $ids
        )));
    }

    private static function normalizeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
