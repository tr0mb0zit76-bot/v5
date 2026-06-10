<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Доплата к марже: заказчик с НДС 0%, перевозчик с НДС 22% (рейс или плечо).
 */
final class VatZeroCustomerStandardVatCarrierMarginSupplement
{
    private const CUSTOMER_VAT_PERCENT = 0.0;

    private const CARRIER_VAT_PERCENT = 22.0;

    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    public static function amount(
        ?string $customerPaymentForm,
        array $contractorsCosts,
        float $supplementPercent = 15.0,
    ): float {
        if (PaymentFormVat::ratePercentForCode($customerPaymentForm) !== self::CUSTOMER_VAT_PERCENT) {
            return 0.0;
        }

        if ($supplementPercent <= 0) {
            return 0.0;
        }

        $eligibleCarrierCost = 0.0;

        foreach (ContractorCostRowClassification::carrierLegCostsOnly($contractorsCosts) as $row) {
            $paymentForm = (string) ($row['payment_form'] ?? '');

            if (PaymentFormVat::ratePercentForCode($paymentForm) !== self::CARRIER_VAT_PERCENT) {
                continue;
            }

            $eligibleCarrierCost += (float) ($row['amount'] ?? 0);
        }

        if ($eligibleCarrierCost <= 0) {
            return 0.0;
        }

        return round($eligibleCarrierCost * ($supplementPercent / 100), 2);
    }
}
