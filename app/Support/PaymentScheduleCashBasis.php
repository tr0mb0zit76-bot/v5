<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Для наличной формы оплаты срок считаем от фактической выгрузки, без ожидания УПД/сканов.
 */
final class PaymentScheduleCashBasis
{
    public static function isCash(?string $paymentForm): bool
    {
        return mb_strtolower(trim((string) $paymentForm)) === 'cash';
    }

    public static function effectiveBasis(?string $paymentForm, string $basis): string
    {
        $basis = strtolower(trim($basis));

        if (! self::isCash($paymentForm)) {
            return $basis;
        }

        if (in_array($basis, ['fttn', 'fttn_receipt', 'ottn'], true)) {
            return 'unloading';
        }

        return $basis;
    }
}
