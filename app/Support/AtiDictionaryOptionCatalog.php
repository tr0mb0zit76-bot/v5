<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AtiDictionaryItem;
use Illuminate\Support\Facades\Schema;

final class AtiDictionaryOptionCatalog
{
    /**
     * @param  list<array{value:int, code:string, label:string}>  $fallback
     * @return list<array{value:int, code:string|null, label:string, ati_id:int|null}>
     */
    public static function options(string $dictionary, array $fallback): array
    {
        if (! Schema::hasTable('ati_dictionary_items')) {
            return self::mapFallbackOptions($fallback);
        }

        $items = AtiDictionaryItem::query()
            ->where('dictionary', $dictionary)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'ati_id', 'code', 'label']);

        if ($items->isEmpty()) {
            return self::mapFallbackOptions($fallback);
        }

        $options = $items
            ->map(fn (AtiDictionaryItem $item): array => [
                'value' => $item->ati_id ?? $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'ati_id' => $item->ati_id,
            ])
            ->values()
            ->all();

        return self::mergeFallbackOptions($options, $fallback);
    }

    /**
     * @param  list<array{value:int, code:string|null, label:string, ati_id:int|null}>  $options
     * @param  list<array{value:int, code:string, label:string}>  $fallback
     * @return list<array{value:int, code:string|null, label:string, ati_id:int|null}>
     */
    private static function mergeFallbackOptions(array $options, array $fallback): array
    {
        $codesInDatabase = collect($options)
            ->pluck('code')
            ->filter(fn (?string $code): bool => filled($code))
            ->flip();

        foreach ($fallback as $item) {
            $code = $item['code'] ?? null;
            if (! is_string($code) || $code === '' || $codesInDatabase->has($code)) {
                continue;
            }

            $options[] = [
                'value' => $item['value'],
                'code' => $item['code'],
                'label' => $item['label'],
                'ati_id' => $item['value'],
            ];
        }

        return collect($options)
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{value:int, code:string, label:string}>  $fallback
     * @return list<array{value:int, code:string|null, label:string, ati_id:int|null}>
     */
    private static function mapFallbackOptions(array $fallback): array
    {
        return array_map(
            fn (array $item): array => [
                'value' => $item['value'],
                'code' => $item['code'],
                'label' => $item['label'],
                'ati_id' => $item['value'],
            ],
            $fallback
        );
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    public static function fallbackCargoTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'general', 'label' => 'Общий груз'],
            ['value' => 2, 'code' => 'dangerous', 'label' => 'Опасный груз'],
            ['value' => 3, 'code' => 'temperature_controlled', 'label' => 'Температурный режим'],
            ['value' => 4, 'code' => 'oversized', 'label' => 'Негабаритный груз'],
            ['value' => 5, 'code' => 'fragile', 'label' => 'Хрупкий груз'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    public static function fallbackPackageTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'pallet', 'label' => 'Паллета'],
            ['value' => 2, 'code' => 'box', 'label' => 'Короб'],
            ['value' => 3, 'code' => 'crate', 'label' => 'Ящик'],
            ['value' => 4, 'code' => 'roll', 'label' => 'Рулон'],
            ['value' => 5, 'code' => 'bag', 'label' => 'Мешок'],
            ['value' => 6, 'code' => 'barrel', 'label' => 'Бочки'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    public static function fallbackLoadingTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'rear', 'label' => 'Задняя'],
            ['value' => 2, 'code' => 'side', 'label' => 'Боковая'],
            ['value' => 3, 'code' => 'top', 'label' => 'Верхняя'],
            ['value' => 4, 'code' => 'full', 'label' => 'Полная растентовка'],
            ['value' => 5, 'code' => 'tail_lift', 'label' => 'Гидроборт'],
            ['value' => 6, 'code' => 'crane', 'label' => 'Манипулятор'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    public static function fallbackTruckBodyTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'all_closed', 'label' => 'Все закрытые'],
            ['value' => 2, 'code' => 'all_open', 'label' => 'Все открытые'],
            ['value' => 3, 'code' => 'tent', 'label' => 'Тент'],
            ['value' => 4, 'code' => 'isothermal', 'label' => 'Изотерм'],
            ['value' => 5, 'code' => 'refrigerator', 'label' => 'Рефрижератор'],
            ['value' => 6, 'code' => 'container', 'label' => 'Контейнеровоз'],
            ['value' => 7, 'code' => 'flatbed', 'label' => 'Бортовой'],
            ['value' => 8, 'code' => 'all_metal', 'label' => 'Цельнометаллический'],
        ];
    }

    /**
     * @return list<array{value:int, code:string, label:string}>
     */
    public static function fallbackTrailerTypeOptions(): array
    {
        return [
            ['value' => 1, 'code' => 'semi_trailer', 'label' => 'Полуприцеп'],
            ['value' => 2, 'code' => 'trailer', 'label' => 'Прицеп'],
            ['value' => 3, 'code' => 'road_train', 'label' => 'Автопоезд'],
            ['value' => 4, 'code' => 'solo', 'label' => 'Одиночная машина'],
        ];
    }
}
