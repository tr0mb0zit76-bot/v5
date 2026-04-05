<?php

namespace App\Services;

use App\Models\Order;

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
        $carrierPaymentForm = $order instanceof Order
            ? $order->carrier_payment_form
            : ($order['carrier_payment_form'] ?? null);

        if (blank($customerPaymentForm) || blank($carrierPaymentForm)) {
            return 'unknown';
        }

        return $customerPaymentForm === $carrierPaymentForm ? 'direct' : 'indirect';
    }
}
