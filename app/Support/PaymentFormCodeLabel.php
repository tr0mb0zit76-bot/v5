<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Человекочитаемые подписи кодов формы оплаты (заказы, печатные формы, грид).
 */
final class PaymentFormCodeLabel
{
    public static function toDisplay(?string $code): ?string
    {
        return PaymentFormDictionary::labelForCode($code);
    }
}
