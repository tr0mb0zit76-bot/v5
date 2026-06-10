<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Категории KPI по формам оплаты заказчика и перевозчиков (с 01.06).
 *
 * — vat_zero_cash: заказчик с НДС 0%, все перевозчики — наличные;
 * — cash: наличка при наличных у всех перевозчиков, заказчик не с НДС 0%;
 * — vat_zero_22: заказчик с НДС 0%, у перевозчика (рейс или плечо) — НДС 22%;
 * — vat_all: заказчик с НДС и у всех перевозчиков — с НДС;
 * — vat: прочие сочетания (в т.ч. НДС 0% / НДС 0%, без НДС у одной стороны, смешанные ставки).
 */
final class KpiPaymentCategoryResolver
{
    private const CUSTOMER_VAT_ZERO_PERCENT = 0.0;

    private const CARRIER_VAT_STANDARD_PERCENT = 22.0;

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    public static function resolve(?string $customerPaymentForm, array $carrierPaymentForms): string
    {
        $customer = mb_strtolower(trim((string) $customerPaymentForm), 'UTF-8');

        if ($customer === '') {
            return 'unknown';
        }

        $carriers = self::normalizeCarrierForms($carrierPaymentForms);

        if ($carriers === []) {
            return 'unknown';
        }

        if (self::isVatZeroCustomerAllCashCarriersDeal($customer, $carriers)) {
            return 'vat_zero_cash';
        }

        if (self::isCashDeal($customer, $carriers)) {
            return 'cash';
        }

        if (self::isVatZeroCustomerStandardVatCarrierDeal($customer, $carriers)) {
            return 'vat_zero_22';
        }

        if (self::isAllPartiesVatDeal($customer, $carriers)) {
            return 'vat_all';
        }

        return 'vat';
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     * @return list<string>
     */
    private static function normalizeCarrierForms(array $carrierPaymentForms): array
    {
        return collect($carrierPaymentForms)
            ->map(fn (mixed $value): string => mb_strtolower(trim((string) $value), 'UTF-8'))
            ->filter(fn (string $value): bool => $value !== '' && $value !== 'mixed')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function isVatZeroCustomerAllCashCarriersDeal(string $customerPaymentForm, array $carrierPaymentForms): bool
    {
        if (PaymentFormVat::ratePercentForCode($customerPaymentForm) !== self::CUSTOMER_VAT_ZERO_PERCENT) {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if ($carrierForm !== 'cash') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function isCashDeal(string $customerPaymentForm, array $carrierPaymentForms): bool
    {
        if (PaymentFormVat::ratePercentForCode($customerPaymentForm) === self::CUSTOMER_VAT_ZERO_PERCENT) {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if ($carrierForm !== 'cash') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function isVatZeroCustomerStandardVatCarrierDeal(string $customerPaymentForm, array $carrierPaymentForms): bool
    {
        if (PaymentFormVat::ratePercentForCode($customerPaymentForm) !== self::CUSTOMER_VAT_ZERO_PERCENT) {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if (PaymentFormVat::ratePercentForCode($carrierForm) === self::CARRIER_VAT_STANDARD_PERCENT) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function isAllPartiesVatDeal(string $customerPaymentForm, array $carrierPaymentForms): bool
    {
        if (! self::hasPositiveVatRate($customerPaymentForm)) {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if (! self::hasPositiveVatRate($carrierForm)) {
                return false;
            }
        }

        return true;
    }

    private static function hasPositiveVatRate(string $paymentForm): bool
    {
        if (! PaymentFormVat::isVatCode($paymentForm)) {
            return false;
        }

        $rate = PaymentFormVat::ratePercentForCode($paymentForm);

        return $rate !== null && $rate > 0;
    }
}
