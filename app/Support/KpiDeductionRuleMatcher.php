<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\KpiDeductionRule;

/**
 * Проверяет, подходит ли заказ под условие вычета.
 */
final class KpiDeductionRuleMatcher
{
    /**
     * @param  list<string>  $carrierPaymentForms
     */
    public static function matches(
        KpiDeductionRule $rule,
        ?string $customerPaymentForm,
        array $carrierPaymentForms,
    ): bool {
        $customer = mb_strtolower(trim((string) $customerPaymentForm), 'UTF-8');
        $carriers = self::normalizeCarrierForms($carrierPaymentForms);

        if ($customer === '' || $carriers === []) {
            return false;
        }

        if (! self::customerMatches($rule, $customer)) {
            return false;
        }

        return self::carrierMatches($rule, $carriers);
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
            ->values()
            ->all();
    }

    private static function customerMatches(KpiDeductionRule $rule, string $customerPaymentForm): bool
    {
        $expectedForm = filled($rule->customer_payment_form)
            ? mb_strtolower(trim((string) $rule->customer_payment_form), 'UTF-8')
            : null;

        if ($expectedForm !== null && $customerPaymentForm !== $expectedForm) {
            return false;
        }

        if ($rule->customer_vat_rate_percent !== null) {
            $expectedRate = round((float) $rule->customer_vat_rate_percent, 2);
            $actualRate = PaymentFormVat::ratePercentForCode($customerPaymentForm);

            if ($actualRate === null || round($actualRate, 2) !== $expectedRate) {
                return false;
            }
        }

        if ($rule->customer_positive_vat_required && ! self::hasPositiveVatRate($customerPaymentForm)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function carrierMatches(KpiDeductionRule $rule, array $carrierPaymentForms): bool
    {
        return match ((string) $rule->carrier_rule) {
            KpiDeductionCarrierRule::ALL_CASH => self::allCarriersAre($carrierPaymentForms, 'cash'),
            KpiDeductionCarrierRule::ALL_EXACT => self::allCarriersAre(
                $carrierPaymentForms,
                self::firstCarrierForm($rule),
            ),
            KpiDeductionCarrierRule::ALL_IN => self::allCarriersInList(
                $carrierPaymentForms,
                self::carrierFormsList($rule),
            ),
            KpiDeductionCarrierRule::ANY_EXACT => self::anyCarrierInList(
                $carrierPaymentForms,
                self::carrierFormsList($rule),
            ),
            KpiDeductionCarrierRule::ALL_POSITIVE_VAT => self::allCarriersHavePositiveVat($carrierPaymentForms),
            KpiDeductionCarrierRule::ANY_VAT_RATE => self::anyCarrierHasVatRate(
                $carrierPaymentForms,
                $rule->carrier_vat_rate_percent !== null ? (float) $rule->carrier_vat_rate_percent : null,
            ),
            KpiDeductionCarrierRule::ANY => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    private static function carrierFormsList(KpiDeductionRule $rule): array
    {
        return collect(is_array($rule->carrier_payment_forms) ? $rule->carrier_payment_forms : [])
            ->map(fn (mixed $value): string => mb_strtolower(trim((string) $value), 'UTF-8'))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    private static function firstCarrierForm(KpiDeductionRule $rule): ?string
    {
        $forms = self::carrierFormsList($rule);

        return $forms[0] ?? null;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function allCarriersAre(array $carrierPaymentForms, ?string $expectedForm): bool
    {
        if ($expectedForm === null || $expectedForm === '') {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if ($carrierForm !== $expectedForm) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     * @param  list<string>  $allowedForms
     */
    private static function allCarriersInList(array $carrierPaymentForms, array $allowedForms): bool
    {
        if ($allowedForms === []) {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if (! in_array($carrierForm, $allowedForms, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     * @param  list<string>  $allowedForms
     */
    private static function anyCarrierInList(array $carrierPaymentForms, array $allowedForms): bool
    {
        if ($allowedForms === []) {
            return false;
        }

        foreach ($carrierPaymentForms as $carrierForm) {
            if (in_array($carrierForm, $allowedForms, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function allCarriersHavePositiveVat(array $carrierPaymentForms): bool
    {
        foreach ($carrierPaymentForms as $carrierForm) {
            if (! self::hasPositiveVatRate($carrierForm)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    private static function anyCarrierHasVatRate(array $carrierPaymentForms, ?float $expectedRate): bool
    {
        if ($expectedRate === null) {
            return false;
        }

        $expected = round($expectedRate, 2);

        foreach ($carrierPaymentForms as $carrierForm) {
            $actual = PaymentFormVat::ratePercentForCode($carrierForm);

            if ($actual !== null && round($actual, 2) === $expected) {
                return true;
            }
        }

        return false;
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
