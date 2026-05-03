<?php

namespace App\Services;

use App\Models\Cargo;

class AtiCargoPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Cargo $cargo): array
    {
        $payload = array_filter([
            'name' => $cargo->ati_cargo_name ?: $cargo->title,
            'cargoTypeId' => $cargo->cargo_type_id,
            'cargoType' => $cargo->cargo_type,
            'cargoTypeName' => $cargo->cargo_type_label,
            'weight' => $this->weightPayload($cargo),
            'volume' => $this->nullableFloat($cargo->volume),
            'sizes' => $this->sizesPayload($cargo),
            'packaging' => $this->packagingPayload($cargo),
            'loading' => $this->loadingPayload($cargo),
            'transport' => $this->transportPayload($cargo),
            'hazard' => $this->hazardPayload($cargo),
            'temperature' => $this->temperaturePayload($cargo),
            'flags' => $this->flagsPayload($cargo),
            'hsCode' => $cargo->hs_code,
            'description' => $cargo->description,
            'specialInstructions' => $cargo->special_instructions,
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');

        return array_replace_recursive($payload, is_array($cargo->ati_cargo_payload) ? $cargo->ati_cargo_payload : []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function weightPayload(Cargo $cargo): ?array
    {
        $value = $this->nullableFloat($cargo->weight_value ?? $cargo->weight);
        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
            'unit' => $cargo->weight_unit ?: 'kg',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sizesPayload(Cargo $cargo): ?array
    {
        $payload = array_filter([
            'length' => $this->nullableFloat($cargo->length),
            'width' => $this->nullableFloat($cargo->width),
            'height' => $this->nullableFloat($cargo->height),
            'diameter' => $this->nullableFloat($cargo->diameter),
            'unit' => 'm',
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return count($payload) > 1 ? $payload : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function packagingPayload(Cargo $cargo): ?array
    {
        $payload = array_filter([
            'packTypeId' => $cargo->pack_type_id,
            'packType' => $cargo->packing_type,
            'packTypeName' => $cargo->pack_type_label,
            'places' => $cargo->package_count ?? $cargo->pallet_count,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadingPayload(Cargo $cargo): ?array
    {
        $items = $this->dictionaryItemsPayload($cargo->loading_type_items);
        $payload = array_filter([
            'loadingTypeId' => $cargo->loading_type_id,
            'loadingType' => $cargo->loading_type_code,
            'loadingTypeName' => $cargo->loading_type_label,
            'loadingTypes' => $items,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function transportPayload(Cargo $cargo): ?array
    {
        $bodyTypes = $this->dictionaryItemsPayload($cargo->truck_body_type_items);
        $trailerTypes = $this->dictionaryItemsPayload($cargo->trailer_type_items);
        $payload = array_filter([
            'truckBodyTypeId' => $cargo->truck_body_type_id,
            'truckBodyType' => $cargo->truck_body_type_code,
            'truckBodyTypeName' => $cargo->truck_body_type_label,
            'truckBodyTypes' => $bodyTypes,
            'trailerTypeId' => $cargo->trailer_type_id,
            'trailerType' => $cargo->trailer_type_code,
            'trailerTypeName' => $cargo->trailer_type_label,
            'trailerTypes' => $trailerTypes,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $payload === [] ? null : $payload;
    }

    /**
     * @return list<array{id:int|null, code:string|null, label:string|null>>
     */
    private function dictionaryItemsPayload(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'id' => $item['id'] ?? null,
                'code' => $item['code'] ?? null,
                'label' => $item['label'] ?? null,
            ])
            ->filter(fn (array $item): bool => $item['id'] !== null || $item['code'] !== null || $item['label'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hazardPayload(Cargo $cargo): ?array
    {
        if (! $cargo->is_hazardous && empty($cargo->hazard_class)) {
            return null;
        }

        return array_filter([
            'isHazardous' => (bool) $cargo->is_hazardous,
            'class' => $cargo->hazard_class,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function temperaturePayload(Cargo $cargo): ?array
    {
        if (! $cargo->needs_temperature && $cargo->temp_min === null && $cargo->temp_max === null) {
            return null;
        }

        return array_filter([
            'required' => (bool) $cargo->needs_temperature,
            'min' => $this->nullableFloat($cargo->temp_min),
            'max' => $this->nullableFloat($cargo->temp_max),
            'unit' => 'C',
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, bool>|null
     */
    private function flagsPayload(Cargo $cargo): ?array
    {
        $flags = array_filter([
            'oversized' => (bool) $cargo->is_oversized,
            'fragile' => (bool) $cargo->is_fragile,
            'hydraulicLiftRequired' => (bool) $cargo->needs_hydraulic,
            'manipulatorRequired' => (bool) $cargo->needs_manipulator,
        ]);

        return $flags === [] ? null : $flags;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
