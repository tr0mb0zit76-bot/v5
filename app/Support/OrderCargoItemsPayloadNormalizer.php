<?php

namespace App\Support;

use Illuminate\Http\Request;

final class OrderCargoItemsPayloadNormalizer
{
    /**
     * Позиция с заполненными данными, но без наименования — раньше молча отбрасывалась при sync.
     *
     * @param  array<string, mixed>  $item
     */
    public static function cargoItemHasSubstance(array $item): bool
    {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name !== '') {
            return true;
        }

        foreach (['description', 'dangerous_class', 'hs_code', 'pack_type_label', 'loading_type_label', 'truck_body_type_label', 'trailer_type_label'] as $textKey) {
            if (trim((string) ($item[$textKey] ?? '')) !== '') {
                return true;
            }
        }

        foreach ([
            'weight_value', 'weight_kg', 'volume_m3', 'package_count',
            'length_value', 'width_value', 'height_value',
            'length_m', 'width_m', 'height_m', 'diameter_m',
            'pack_type_id', 'loading_type_id', 'truck_body_type_id', 'trailer_type_id',
        ] as $numericKey) {
            $raw = $item[$numericKey] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            if (is_numeric($raw) && (float) $raw > 0) {
                return true;
            }
        }

        foreach (['loading_type_items', 'truck_body_type_items', 'trailer_type_items', 'performer_allocations'] as $listKey) {
            if (is_array($item[$listKey] ?? null) && $item[$listKey] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function cargoItemRequiresName(array $item): bool
    {
        return self::cargoItemHasSubstance($item) && trim((string) ($item['name'] ?? '')) === '';
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    public static function normalizeFromRequestInput(mixed $rawCargoItems, array $performers): array
    {
        if (! is_array($rawCargoItems)) {
            return [];
        }

        return collect($rawCargoItems)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $item): array => self::normalizeCargoItem($item, $performers))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    public static function normalizeValidatedCargoItems(array $validated, Request $request): array
    {
        $performers = is_array($validated['performers'] ?? null)
            ? array_values(array_filter($validated['performers'], static fn (mixed $row): bool => is_array($row)))
            : [];

        $rawItems = $request->input('cargo_items');
        if (! is_array($rawItems) || $rawItems === []) {
            $rawItems = self::cargoItemsFromOrderPayload($request);
        }

        if (is_array($rawItems) && $rawItems !== []) {
            return self::normalizeFromRequestInput($rawItems, $performers);
        }

        $validatedItems = $validated['cargo_items'] ?? [];

        return is_array($validatedItems)
            ? collect($validatedItems)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $item): array => self::normalizeCargoItem($item, $performers))
                ->values()
                ->all()
            : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function cargoItemsFromOrderPayload(Request $request): array
    {
        if (! $request->has('order_payload')) {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($request->string('order_payload')->value(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['cargo_items'] ?? null;

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $performers
     * @return array<string, mixed>
     */
    public static function normalizeCargoItem(array $item, array $performers): array
    {
        $item = self::mergePerformerAllocationsIntoCargoItem($item);
        $allocations = CargoPerformerAllocationBuilder::resolveForCargoItem($item, $performers);

        if ($allocations === []) {
            return $item;
        }

        $item['performer_allocations'] = $allocations;

        $atiPayload = $item['ati_cargo_payload'] ?? null;
        if (! is_array($atiPayload) || $atiPayload === [] || array_is_list($atiPayload)) {
            $atiPayload = [];
        }

        $item['ati_cargo_payload'] = array_merge($atiPayload, [
            'performer_allocations' => $allocations,
        ]);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function mergePerformerAllocationsIntoCargoItem(array $item): array
    {
        $fromRoot = $item['performer_allocations'] ?? null;
        if (is_array($fromRoot) && $fromRoot !== []) {
            return $item;
        }

        $atiPayload = $item['ati_cargo_payload'] ?? null;
        if (! is_array($atiPayload) || array_is_list($atiPayload)) {
            return $item;
        }

        $fromAti = $atiPayload['performer_allocations'] ?? null;
        if (is_array($fromAti) && $fromAti !== []) {
            $item['performer_allocations'] = $fromAti;
        }

        return $item;
    }
}
