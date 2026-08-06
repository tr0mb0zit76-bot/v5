<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Наличная форма оплаты:
 * — «по сканам» (fttn) — срок от выгрузки;
 * — у перевозчика/подрядчика «по оригиналам» (ottn) / «сканы + квиток» (fttn_receipt) — срок от ТСД/ТН;
 * — у заказчика ottn / fttn_receipt — как у безнала: дата получения оригиналов.
 */
final class PaymentScheduleCashBasis
{
    public static function isCash(?string $paymentForm): bool
    {
        return mb_strtolower(trim((string) $paymentForm)) === 'cash';
    }

    public static function effectiveBasis(?string $paymentForm, string $basis, ?string $party = null): string
    {
        $basis = strtolower(trim($basis));
        $party = $party !== null ? strtolower(trim($party)) : null;

        if (! self::isCash($paymentForm)) {
            return $basis;
        }

        if ($basis === 'fttn') {
            return 'unloading';
        }

        // Нал перевозчику/подрядчику: дата оплаты от товаросопроводительного документа, не от заявки.
        if (
            in_array($party, ['carrier', 'contractor'], true)
            && in_array($basis, ['ottn', 'fttn_receipt'], true)
        ) {
            return 'waybill';
        }

        return $basis;
    }
}
