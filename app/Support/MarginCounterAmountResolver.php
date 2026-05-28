<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Суммы для считалки: «как в переговорах» (введённое поле) и «как в заказе» (без НДС).
 */
final class MarginCounterAmountResolver
{
    public const BASIS_NEGOTIATION = 'negotiation';

    public const BASIS_ORDER_NET = 'order_net';

    /**
     * База для вычета KPI и процента маржи (доход заказчика).
     */
    public static function customerRevenue(
        string $anchor,
        ?float $without,
        ?float $with,
        string $customerPaymentForm,
        string $basis,
    ): ?float {
        if ($basis === self::BASIS_ORDER_NET) {
            return self::resolveOrderNet($anchor, $without, $with, $customerPaymentForm);
        }

        return self::resolveNegotiationCustomer($anchor, $without, $with, $customerPaymentForm);
    }

    /**
     * Расход на перевозчика в формуле маржи.
     */
    public static function carrierExpense(
        string $anchor,
        ?float $without,
        ?float $with,
        string $carrierPaymentForm,
        string $basis,
    ): ?float {
        if ($basis === self::BASIS_ORDER_NET) {
            return self::resolveOrderNet($anchor, $without, $with, $carrierPaymentForm);
        }

        return self::resolveNegotiationCarrier($anchor, $without, $with, $carrierPaymentForm);
    }

    private static function resolveNegotiationCustomer(
        string $anchor,
        ?float $without,
        ?float $with,
        string $paymentForm,
    ): ?float {
        if ($anchor === 'customer_with_vat' && $with !== null) {
            return max(0.0, $with);
        }

        if ($anchor === 'customer_without_vat' && $without !== null) {
            return max(0.0, $without);
        }

        if (PaymentFormVat::isVatCode($paymentForm) && $with !== null) {
            return max(0.0, $with);
        }

        if ($without !== null) {
            return max(0.0, $without);
        }

        if ($with !== null) {
            return max(0.0, $with);
        }

        return null;
    }

    private static function resolveNegotiationCarrier(
        string $anchor,
        ?float $without,
        ?float $with,
        string $paymentForm,
    ): ?float {
        if (PaymentFormVat::isVatCode($paymentForm)) {
            if ($with !== null) {
                return max(0.0, $with);
            }

            if ($without !== null) {
                $pair = PaymentAmountVatConverter::pairFromNet($without, $paymentForm);

                return (float) ($pair['with_vat'] ?? $without);
            }

            return null;
        }

        if ($anchor === 'carrier_without_vat' && $without !== null) {
            return max(0.0, $without);
        }

        if ($anchor === 'carrier_with_vat' && $with !== null) {
            return max(0.0, $with);
        }

        if ($without !== null) {
            return max(0.0, $without);
        }

        if ($with !== null) {
            return max(0.0, PaymentAmountVatConverter::netFromGrossAmount($with, $paymentForm));
        }

        return null;
    }

    private static function resolveOrderNet(
        string $anchor,
        ?float $without,
        ?float $with,
        string $paymentForm,
    ): ?float {
        $withoutAnchor = str_contains($anchor, 'without') ? $anchor : '';
        $withAnchor = str_contains($anchor, 'with') ? $anchor : '';

        if ($withoutAnchor !== '' && $anchor === $withoutAnchor && $without !== null) {
            return max(0.0, $without);
        }

        if ($withAnchor !== '' && $anchor === $withAnchor && $with !== null) {
            return max(0.0, PaymentAmountVatConverter::netFromGrossAmount($with, $paymentForm));
        }

        if ($without !== null) {
            return max(0.0, $without);
        }

        if ($with !== null) {
            return max(0.0, PaymentAmountVatConverter::netFromGrossAmount($with, $paymentForm));
        }

        return null;
    }
}
