<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\KpiDeductionRule;

/**
 * Человекочитаемое описание условия вычета.
 */
final class KpiDeductionRuleDescription
{
    public static function build(KpiDeductionRule $rule): string
    {
        $parts = [];

        if (filled($rule->customer_payment_form)) {
            $label = PaymentFormDictionary::labelForCode((string) $rule->customer_payment_form) ?? $rule->customer_payment_form;
            $parts[] = 'заказчик: '.$label;
        } elseif ($rule->customer_vat_rate_percent !== null) {
            $parts[] = 'заказчик: НДС '.self::formatPercent((float) $rule->customer_vat_rate_percent).'%';
        } elseif ($rule->customer_positive_vat_required) {
            $parts[] = 'заказчик: с НДС > 0%';
        } else {
            $parts[] = 'заказчик: любая форма';
        }

        $parts[] = self::carrierPart($rule);

        return implode(' · ', $parts);
    }

    private static function carrierPart(KpiDeductionRule $rule): string
    {
        $carrierRule = (string) $rule->carrier_rule;
        $forms = is_array($rule->carrier_payment_forms) ? $rule->carrier_payment_forms : [];

        return match ($carrierRule) {
            KpiDeductionCarrierRule::ALL_CASH => 'перевозчики: все наличные',
            KpiDeductionCarrierRule::ALL_EXACT => 'перевозчики: все '.self::formLabel($forms[0] ?? '—'),
            KpiDeductionCarrierRule::ALL_IN => 'перевозчики: все из ['.self::formLabelsJoined($forms).']',
            KpiDeductionCarrierRule::ANY_EXACT => 'перевозчики: хотя бы один из ['.self::formLabelsJoined($forms).']',
            KpiDeductionCarrierRule::ALL_POSITIVE_VAT => 'перевозчики: все с НДС > 0%',
            KpiDeductionCarrierRule::ANY_VAT_RATE => 'перевозчики: хотя бы один с НДС '.self::formatPercent((float) $rule->carrier_vat_rate_percent).'%',
            KpiDeductionCarrierRule::ANY => 'перевозчики: любые',
            default => 'перевозчики: '.$carrierRule,
        };
    }

    /**
     * @param  list<mixed>  $forms
     */
    private static function formLabelsJoined(array $forms): string
    {
        return collect($forms)
            ->map(fn (mixed $form): string => self::formLabel((string) $form))
            ->implode(', ');
    }

    private static function formLabel(string $code): string
    {
        return PaymentFormDictionary::labelForCode($code) ?? $code;
    }

    private static function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
