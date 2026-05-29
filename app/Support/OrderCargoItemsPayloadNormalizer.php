<?php

namespace App\Support;

use Illuminate\Http\Request;

final class OrderCargoItemsPayloadNormalizer
{
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
