<?php

namespace App\Support;

use App\Models\Currency;
use Illuminate\Support\Facades\Schema;

final class CurrencyDictionary
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        if (! Schema::hasTable('currencies')) {
            return self::fallbackOptions();
        }

        $rows = Currency::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['code', 'name']);

        if ($rows->isEmpty()) {
            return self::fallbackOptions();
        }

        return $rows
            ->map(fn (Currency $currency): array => [
                'value' => $currency->code,
                'label' => $currency->code.' — '.$currency->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function allowedCodes(): array
    {
        $codes = array_values(array_unique(array_map(
            static fn (array $row): string => $row['value'],
            self::options()
        )));

        return $codes;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function fallbackOptions(): array
    {
        return [
            ['value' => 'RUB', 'label' => 'RUB — Российский рубль'],
            ['value' => 'USD', 'label' => 'USD — Доллар США'],
            ['value' => 'CNY', 'label' => 'CNY — Китайский юань'],
            ['value' => 'EUR', 'label' => 'EUR — Евро'],
        ];
    }
}
