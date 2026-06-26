<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Наличная форма оплаты: «по сканам» (fttn) — срок от выгрузки.
 * «По оригиналам» (ottn) и «сканы + квиток» (fttn_receipt) — как у безнала: дата получения + якорь/сдвиг.
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

        if ($basis === 'fttn') {
            return 'unloading';
        }

        return $basis;
    }
}
