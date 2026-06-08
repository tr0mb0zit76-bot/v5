<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Режим сопоставления форм оплаты перевозчиков в правиле вычета.
 */
final class KpiDeductionCarrierRule
{
    public const ALL_CASH = 'all_cash';

    public const ALL_EXACT = 'all_exact';

    public const ALL_IN = 'all_in';

    public const ANY_EXACT = 'any_exact';

    public const ALL_POSITIVE_VAT = 'all_positive_vat';

    public const ANY_VAT_RATE = 'any_vat_rate';

    public const ANY = 'any';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ALL_CASH,
            self::ALL_EXACT,
            self::ALL_IN,
            self::ANY_EXACT,
            self::ALL_POSITIVE_VAT,
            self::ANY_VAT_RATE,
            self::ANY,
        ];
    }

    public static function label(string $rule): string
    {
        return match ($rule) {
            self::ALL_CASH => 'Все перевозчики — наличные',
            self::ALL_EXACT => 'Все перевозчики — одна форма',
            self::ALL_IN => 'Все перевозчики — из списка',
            self::ANY_EXACT => 'Хотя бы один перевозчик — из списка',
            self::ALL_POSITIVE_VAT => 'Все перевозчики — с НДС > 0%',
            self::ANY_VAT_RATE => 'Хотя бы один перевозчик — ставка НДС',
            self::ANY => 'Любые перевозчики',
            default => $rule,
        };
    }
}
