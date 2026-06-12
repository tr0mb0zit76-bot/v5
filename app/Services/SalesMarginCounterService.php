<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KpiDeductionRule;
use App\Support\KpiDeductionCarrierRule;
use App\Support\KpiDeductionRuleAmount;
use App\Support\KpiDeductionRuleDescription;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentFormVat;

class SalesMarginCounterService
{
    public function __construct(
        private readonly KpiConfigurationService $kpiConfigurationService,
    ) {}

    /**
     * @return list<array{id: int, name: string, description: string, deduction_label: string}>
     */
    public function deductionRuleOptionsForDate(string $date): array
    {
        return KpiDeductionRule::activeForDate($date)
            ->map(fn (KpiDeductionRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => KpiDeductionRuleDescription::build($rule),
                'deduction_label' => KpiDeductionRuleAmount::ratesLabel($rule),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $ruleId = (int) ($input['kpi_deduction_rule_id'] ?? 0);
        $rule = KpiDeductionRule::query()->find($ruleId);

        if (! $rule instanceof KpiDeductionRule || ! $rule->is_active) {
            return [
                'error' => 'Выберите действующее условие вычета.',
            ];
        }

        $customerRate = $this->nullableAmount($input['customer_rate'] ?? null);
        $carrierRate = $this->nullableAmount($input['carrier_rate'] ?? null);
        $bonus = max(0.0, (float) ($input['bonus'] ?? 0));
        $additionalExpenses = max(0.0, (float) ($input['additional_expenses'] ?? 0));
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();

        if ($customerRate === null || $customerRate <= 0) {
            return [
                'warning' => 'Укажите ставку заказчика.',
            ];
        }

        if ($carrierRate === null) {
            return [
                'warning' => 'Укажите ставку перевозчика.',
            ];
        }

        $kpiDeduction = KpiDeductionRuleAmount::deductionAmount($rule, $customerRate);
        $ratesLabel = KpiDeductionRuleAmount::ratesLabel($rule);
        $fixedExpense = round($carrierRate + $additionalExpenses + ($bonus * $bonusMultiplier), 2);

        $customerPaymentForm = filled($rule->customer_payment_form)
            ? (string) $rule->customer_payment_form
            : PaymentFormDictionary::defaultClientVatCode();
        $carrierPaymentForm = $this->inferCarrierPaymentForm($rule);

        $marginSupplement = KpiDeductionRuleAmount::marginSupplementAmount(
            $rule,
            $customerPaymentForm,
            [
                ['payment_form' => $carrierPaymentForm, 'amount' => $carrierRate],
            ],
        );

        $margin = round($customerRate - $kpiDeduction - $fixedExpense + $marginSupplement, 2);
        $marginPercent = $customerRate > 0 ? round(($margin / $customerRate) * 100, 2) : 0.0;

        return [
            'rule' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => KpiDeductionRuleDescription::build($rule),
            ],
            'summary' => [
                'customer_rate' => round($customerRate, 2),
                'carrier_rate' => round($carrierRate, 2),
                'kpi_deduction_amount' => round($kpiDeduction, 2),
                'kpi_deduction_rates_label' => $ratesLabel,
                'fixed_expense' => $fixedExpense,
                'margin_supplement' => round($marginSupplement, 2),
                'margin' => $margin,
                'margin_percent' => $marginPercent,
                'comment' => sprintf(
                    'Заказчик %s ₽, вычет KPI %s ₽ (%s), перевозчик %s ₽, расходы %s ₽. Маржа %s ₽ (%s%%).',
                    $this->formatAmount($customerRate),
                    $this->formatAmount($kpiDeduction),
                    $ratesLabel,
                    $this->formatAmount($carrierRate),
                    $this->formatAmount($fixedExpense),
                    $this->formatAmount($margin),
                    $this->formatPercent($marginPercent),
                ),
            ],
        ];
    }

    private function inferCarrierPaymentForm(KpiDeductionRule $rule): string
    {
        $forms = is_array($rule->carrier_payment_forms) ? $rule->carrier_payment_forms : [];

        if ($forms !== []) {
            return (string) $forms[0];
        }

        if ((string) $rule->carrier_rule === KpiDeductionCarrierRule::ALL_CASH) {
            return 'cash';
        }

        if ($rule->carrier_vat_rate_percent !== null) {
            $expectedRate = round((float) $rule->carrier_vat_rate_percent, 2);

            foreach (['vat_22', 'vat_20', 'vat_0', 'no_vat'] as $code) {
                $rate = PaymentFormVat::ratePercentForCode($code);

                if ($rate !== null && round($rate, 2) === $expectedRate) {
                    return $code;
                }
            }
        }

        if (filled($rule->customer_payment_form)) {
            return (string) $rule->customer_payment_form;
        }

        return PaymentFormDictionary::defaultClientVatCode();
    }

    private function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float >= 0 ? $float : null;
    }

    private function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', ' ');
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
