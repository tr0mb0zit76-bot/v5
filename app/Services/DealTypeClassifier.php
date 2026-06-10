<?php

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\KpiDeductionRule;
use App\Models\Order;
use App\Support\CarrierPaymentFormResolver;
use App\Support\ContractorCostRowClassification;
use Illuminate\Support\Facades\Schema;

class DealTypeClassifier
{
    public function __construct(
        private readonly KpiDeductionRuleResolver $kpiDeductionRuleResolver,
    ) {}

    /**
     * Категория KPI / правило вычета.
     *
     * @param  array<string, mixed>|Order  $order
     */
    public function classify(array|Order $order): string
    {
        return $this->resolve($order)['deal_type'];
    }

    /**
     * @param  array<string, mixed>|Order  $order
     * @return array{
     *     deal_type: string,
     *     deal_type_label: string,
     *     rule: KpiDeductionRule|null,
     *     uses_custom_rules: bool,
     * }
     */
    public function resolve(array|Order $order): array
    {
        $customerPaymentForm = $order instanceof Order
            ? $order->customer_payment_form
            : ($order['customer_payment_form'] ?? null);

        $orderDate = $order instanceof Order
            ? optional($order->order_date)?->toDateString()
            : ($order['order_date'] ?? null);

        return $this->kpiDeductionRuleResolver->resolve(
            is_string($orderDate) ? $orderDate : null,
            is_string($customerPaymentForm) ? $customerPaymentForm : null,
            $this->carrierPaymentForms($order),
        );
    }

    /**
     * @param  array<string, mixed>|Order  $order
     * @return list<string>
     */
    private function carrierPaymentForms(array|Order $order): array
    {
        if ($order instanceof Order) {
            $costs = $this->contractorsCostsFromOrder($order);

            if ($costs !== []) {
                return $this->uniquePaymentFormsFromCosts($costs);
            }

            if (Schema::hasColumn('orders', 'carrier_payment_form')) {
                $carrier = $order->carrier_payment_form;

                if (blank($carrier) || (string) $carrier === 'mixed') {
                    return [];
                }

                return [(string) $carrier];
            }

            $resolved = CarrierPaymentFormResolver::forOrder($order);

            if (blank($resolved) || $resolved === 'mixed') {
                return [];
            }

            return [(string) $resolved];
        }

        $costs = $order['contractors_costs'] ?? null;

        if (is_array($costs) && $costs !== []) {
            return $this->uniquePaymentFormsFromCosts($costs);
        }

        $resolved = $order['carrier_payment_form'] ?? null;

        if (blank($resolved) || (string) $resolved === 'mixed') {
            return [];
        }

        return [(string) $resolved];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contractorsCostsFromOrder(Order $order): array
    {
        if (! Schema::hasTable('financial_terms')) {
            return [];
        }

        if ($order->relationLoaded('financialTerms')) {
            $costs = $order->financialTerms->first()?->contractors_costs;
        } else {
            $costs = FinancialTerm::query()
                ->where('order_id', $order->id)
                ->value('contractors_costs');
        }

        return is_array($costs) ? $costs : [];
    }

    /**
     * @param  list<array<string, mixed>>  $costs
     * @return list<string>
     */
    private function uniquePaymentFormsFromCosts(array $costs): array
    {
        return collect(ContractorCostRowClassification::carrierLegCostsOnly($costs))
            ->pluck('payment_form')
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values()
            ->all();
    }
}
