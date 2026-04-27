<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Человекочитаемые подписи кодов формы оплаты (как в мастере заказа).
 */
final class PaymentFormCodeLabel
{
    public static function toDisplay(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim($code);
        if ($trimmed === '') {
            return null;
        }

        return match (mb_strtolower($trimmed)) {
            'vat' => 'С НДС',
            'no_vat' => 'Без НДС',
            'cash' => 'Нал',
            'mixed' => 'Разные',
            default => $trimmed,
        };
    }
}
