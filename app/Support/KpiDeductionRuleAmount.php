<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\KpiDeductionRule;

/**
 * Сумма вычета и доплаты к марже по правилу.
 */
final class KpiDeductionRuleAmount
{
    public static function deductionAmount(KpiDeductionRule $rule, float $customerRate): float
    {
        if ($customerRate <= 0) {
            return 0.0;
        }

        $primary = (float) $rule->deduction_primary_percent;
        $secondary = $rule->deduction_secondary_percent !== null
            ? (float) $rule->deduction_secondary_percent
            : null;

        if ($secondary !== null && $secondary > 0) {
            return self::sequentialDeduction($customerRate, $primary, $secondary);
        }

        return $customerRate * ($primary / 100);
    }

    public static function effectivePercent(float $customerRate, float $deductionAmount): float
    {
        if ($customerRate <= 0) {
            return 0.0;
        }

        return round(($deductionAmount / $customerRate) * 100, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    public static function marginSupplementAmount(
        KpiDeductionRule $rule,
        ?string $customerPaymentForm,
        array $contractorsCosts,
    ): float {
        $supplementPercent = $rule->margin_supplement_percent !== null
            ? (float) $rule->margin_supplement_percent
            : 0.0;

        if ($supplementPercent <= 0) {
            return 0.0;
        }

        $carrierVatPercent = $rule->margin_supplement_carrier_vat_percent !== null
            ? (float) $rule->margin_supplement_carrier_vat_percent
            : null;

        if ($carrierVatPercent === null) {
            return 0.0;
        }

        if ($rule->customer_vat_rate_percent !== null) {
            $expectedCustomerRate = round((float) $rule->customer_vat_rate_percent, 2);
            $actualCustomerRate = PaymentFormVat::ratePercentForCode($customerPaymentForm);

            if ($actualCustomerRate === null || round($actualCustomerRate, 2) !== $expectedCustomerRate) {
                return 0.0;
            }
        }

        $eligibleCarrierCost = 0.0;
        $expectedCarrierRate = round($carrierVatPercent, 2);

        foreach (ContractorCostRowClassification::carrierLegCostsOnly($contractorsCosts) as $row) {
            $paymentForm = (string) ($row['payment_form'] ?? '');
            $actualRate = PaymentFormVat::ratePercentForCode($paymentForm);

            if ($actualRate === null || round($actualRate, 2) !== $expectedCarrierRate) {
                continue;
            }

            $eligibleCarrierCost += (float) ($row['amount'] ?? 0);
        }

        if ($eligibleCarrierCost <= 0) {
            return 0.0;
        }

        return round($eligibleCarrierCost * ($supplementPercent / 100), 2);
    }

    public static function ratesLabel(KpiDeductionRule $rule): string
    {
        $primary = (float) $rule->deduction_primary_percent;
        $secondary = $rule->deduction_secondary_percent !== null
            ? (float) $rule->deduction_secondary_percent
            : null;

        if ($secondary !== null && $secondary > 0) {
            return sprintf('%s%% + %s%%', self::formatPercent($primary), self::formatPercent($secondary));
        }

        return sprintf('%s%%', self::formatPercent($primary));
    }

    private static function sequentialDeduction(float $customerRate, float $primaryPercent, float $secondaryPercent): float
    {
        $primaryAmount = $customerRate * ($primaryPercent / 100);
        $remainder = $customerRate - $primaryAmount;

        return $primaryAmount + ($remainder * ($secondaryPercent / 100));
    }

    private static function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
