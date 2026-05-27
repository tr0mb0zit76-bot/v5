<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\VatRate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

/**
 * Коды формы оплаты: ставки НДС из справочника + фиксированные {@see no_vat}, {@see cash}.
 * Устаревший код {@see vat} обрабатывается при нормализации и отображении.
 */
final class PaymentFormDictionary
{
    private const LEGACY_VAT = 'vat';

    /**
     * @return list<array{value: string, label: string, is_vat: bool, rate_percent: float|null}>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::vatRatesOrdered() as $row) {
            $out[] = [
                'value' => $row->code,
                'label' => $row->label,
                'is_vat' => true,
                'rate_percent' => (float) $row->rate_percent,
            ];
        }

        $out[] = [
            'value' => 'no_vat',
            'label' => 'Без НДС',
            'is_vat' => false,
            'rate_percent' => null,
        ];
        $out[] = [
            'value' => 'cash',
            'label' => 'Наличные',
            'is_vat' => false,
            'rate_percent' => null,
        ];

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function allowedCodesForValidation(): array
    {
        $codes = self::vatRateCodes();
        $codes[] = 'no_vat';
        $codes[] = 'cash';
        $codes[] = self::LEGACY_VAT;

        return array_values(array_unique($codes));
    }

    /**
     * Первая ставка НДС по порядку справочника — для новых заказов и замены legacy `vat`.
     */
    public static function defaultClientVatCode(): string
    {
        $first = self::vatRatesOrdered()->first();

        if ($first !== null) {
            return (string) $first->code;
        }

        return self::LEGACY_VAT;
    }

    public static function labelForCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim($code);
        if ($trimmed === '') {
            return null;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');

        if ($lower === self::LEGACY_VAT) {
            $fromDb = self::labelFromVatTableByCode(self::LEGACY_VAT);
            if ($fromDb !== null) {
                return $fromDb;
            }

            $first = self::vatRatesOrdered()->first();

            return $first !== null ? (string) $first->label : 'С НДС';
        }

        if ($lower === 'no_vat') {
            return 'Без НДС';
        }

        if ($lower === 'cash') {
            return 'Нал';
        }

        if ($lower === 'mixed') {
            return 'Разные';
        }

        $fromDb = self::labelFromVatTableByCode($trimmed);
        if ($fromDb !== null) {
            return $fromDb;
        }

        return $trimmed;
    }

    /**
     * Нормализация перед сохранением в БД (inline, мастер): legacy `vat` → конкретная ставка.
     */
    public static function normalizeForStorage(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        if (mb_strtolower($trimmed, 'UTF-8') === self::LEGACY_VAT) {
            return self::defaultClientVatCode();
        }

        return $trimmed;
    }

    /**
     * @return EloquentCollection<int, VatRate>
     */
    private static function vatRatesOrdered(): EloquentCollection
    {
        if (! Schema::hasTable('vat_rates')) {
            return new EloquentCollection;
        }

        return VatRate::query()
            ->orderBy('sort_order')
            ->orderByDesc('rate_percent')
            ->get();
    }

    /**
     * @return list<string>
     */
    private static function vatRateCodes(): array
    {
        if (! Schema::hasTable('vat_rates')) {
            return [];
        }

        return VatRate::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('code')
            ->map(fn (mixed $c): string => (string) $c)
            ->values()
            ->all();
    }

    private static function labelFromVatTableByCode(string $code): ?string
    {
        if (! Schema::hasTable('vat_rates')) {
            return null;
        }

        $row = VatRate::query()->where('code', $code)->first(['label']);

        return $row !== null ? (string) $row->label : null;
    }
}
