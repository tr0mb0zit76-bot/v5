<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KpiDeductionRule;
use App\Support\KpiDeductionRuleMatcher;
use App\Support\KpiPaymentCategoryResolver;

/**
 * Определяет применимое правило вычета или legacy-категорию.
 */
class KpiDeductionRuleResolver
{
    /**
     * @param  list<string>  $carrierPaymentForms
     * @return array{
     *     deal_type: string,
     *     deal_type_label: string,
     *     rule: KpiDeductionRule|null,
     *     uses_custom_rules: bool,
     * }
     */
    public function resolve(
        ?string $orderDate,
        ?string $customerPaymentForm,
        array $carrierPaymentForms,
    ): array {
        if (! KpiDeductionRule::usesCustomRulesOnDate($orderDate)) {
            $legacyCategory = KpiPaymentCategoryResolver::resolve($customerPaymentForm, $carrierPaymentForms);

            return [
                'deal_type' => $legacyCategory,
                'deal_type_label' => $this->legacyLabel($legacyCategory),
                'rule' => null,
                'uses_custom_rules' => false,
            ];
        }

        $rule = $this->matchRule($orderDate, $customerPaymentForm, $carrierPaymentForms);

        if ($rule === null) {
            return [
                'deal_type' => 'unknown',
                'deal_type_label' => 'Нет подходящего условия',
                'rule' => null,
                'uses_custom_rules' => true,
            ];
        }

        return [
            'deal_type' => 'rule:'.$rule->id,
            'deal_type_label' => (string) $rule->name,
            'rule' => $rule,
            'uses_custom_rules' => true,
        ];
    }

    /**
     * @param  list<string>  $carrierPaymentForms
     */
    public function matchRule(
        ?string $orderDate,
        ?string $customerPaymentForm,
        array $carrierPaymentForms,
    ): ?KpiDeductionRule {
        if (! KpiDeductionRule::usesCustomRulesOnDate($orderDate)) {
            return null;
        }

        foreach (KpiDeductionRule::activeForDate((string) $orderDate) as $rule) {
            if (KpiDeductionRuleMatcher::matches($rule, $customerPaymentForm, $carrierPaymentForms)) {
                return $rule;
            }
        }

        return null;
    }

    private function legacyLabel(string $category): string
    {
        return match ($category) {
            'cash' => 'Наличка',
            'vat_zero_22' => 'НДС 0% / 22%',
            'vat_zero_cash' => 'НДС 0% / наличные',
            'vat_all' => 'НДС у всех',
            'vat', 'cashless' => 'Прочие НДС',
            default => 'Появится после заполнения оплат',
        };
    }
}
