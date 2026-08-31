<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Наличная форма оплаты:
 * — «по сканам» (fttn) — срок от выгрузки;
 * — у перевозчика/подрядчика «по оригиналам» (ottn) / «сканы + квиток» (fttn_receipt) — срок от ТСД/ТН;
 * — у заказчика ottn / fttn_receipt — как у безнала: дата получения оригиналов;
 * — сделка «нал ↔ нал» (заказчик и все исходящие наличкой) — все документные базисы → выгрузка.
 */
final class PaymentScheduleCashBasis
{
    public static function isCash(?string $paymentForm): bool
    {
        return mb_strtolower(trim((string) $paymentForm)) === 'cash';
    }

    /**
     * Заказчик наличкой и все исходящие (перевозчики / подрядчики) тоже наличкой.
     *
     * @param  array<int|string, string|null>  $carrierPaymentForms
     * @param  list<string|null>  $additionalPaymentForms
     */
    public static function isCashToCashDeal(
        ?string $customerPaymentForm,
        array $carrierPaymentForms = [],
        array $additionalPaymentForms = [],
    ): bool {
        if (! self::isCash($customerPaymentForm)) {
            return false;
        }

        $outgoing = [];

        foreach ($carrierPaymentForms as $form) {
            $outgoing[] = is_string($form) ? $form : null;
        }

        foreach ($additionalPaymentForms as $form) {
            $outgoing[] = is_string($form) ? $form : null;
        }

        if ($outgoing === []) {
            return false;
        }

        foreach ($outgoing as $form) {
            if (! self::isCash($form)) {
                return false;
            }
        }

        return true;
    }

    public static function effectiveBasis(
        ?string $paymentForm,
        string $basis,
        ?string $party = null,
        bool $cashToCashDeal = false,
    ): string {
        $basis = strtolower(trim($basis));
        $party = $party !== null ? strtolower(trim($party)) : null;

        if (! self::isCash($paymentForm)) {
            return $basis;
        }

        // Нал ↔ нал: договорные документные события не нужны — считаем от фактической выгрузки.
        if ($cashToCashDeal && in_array($basis, ['fttn', 'ottn', 'fttn_receipt', 'waybill'], true)) {
            return 'unloading';
        }

        if ($basis === 'fttn') {
            return 'unloading';
        }

        // Нал перевозчику/подрядчику (при безнале заказчику): дата оплаты от товаросопроводительного документа.
        if (
            in_array($party, ['carrier', 'contractor'], true)
            && in_array($basis, ['ottn', 'fttn_receipt'], true)
        ) {
            return 'waybill';
        }

        return $basis;
    }
}
