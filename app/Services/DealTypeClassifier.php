<?php

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Support\CarrierPaymentFormResolver;
use App\Support\PaymentFormVat;
use Illuminate\Support\Facades\Schema;

class DealTypeClassifier
{
    /**
     * @param  array<string, mixed>|Order  $order
     */
    public function classify(array|Order $order): string
    {
        $customerPaymentForm = $order instanceof Order
            ? $order->customer_payment_form
            : ($order['customer_payment_form'] ?? null);

        $carrierPaymentForms = $this->carrierPaymentForms($order);

        if (blank($customerPaymentForm) || $carrierPaymentForms === []) {
            return 'unknown';
        }

        if (PaymentFormVat::isIndirectDeal((string) $customerPaymentForm, $carrierPaymentForms)) {
            return 'indirect';
        }

        return 'direct';
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

        if (blank($resolved) || $resolved === 'mixed') {
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
        return collect($costs)
            ->pluck('payment_form')
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->values()
            ->all();
    }
}
