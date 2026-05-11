<?php

declare(strict_types=1);

namespace App\Support;

final class VatRateCode
{
    /**
     * Стабильный код ставки для колонки `code` (например 22 → vat_22, 5.5 → vat_5_5).
     */
    public static function fromRate(float|string|int $rate): string
    {
        $n = round((float) $rate, 4);
        $s = number_format($n, 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return 'vat_'.str_replace('.', '_', $s);
    }
}
