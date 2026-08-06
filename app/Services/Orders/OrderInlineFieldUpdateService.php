<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCompensationService;
use App\Services\Orders\Wizard\OrderWizardFinancialTermsSyncService;
use App\Services\OrderWizardStateService;
use App\Support\OrderTrackReceivedFields;
use Illuminate\Support\Facades\Schema;

class OrderInlineFieldUpdateService
{
    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
        private readonly OrderWizardStateService $orderWizardStateService,
        private readonly OrderWizardFinancialTermsSyncService $financialTermsSyncService,
    ) {}

    public function apply(User $user, Order $order, string $field, mixed $value): Order
    {
        $previousOrderDate = $order->order_date?->toDateString();

        $fill = [
            'updated_by' => $user->id,
        ];

        if (Schema::hasColumn('orders', $field)) {
            $fill[$field] = $value;
        }

        $order->forceFill($fill)->save();

        $syncOrder = $order->fresh();
        if ($syncOrder === null) {
            return $order;
        }

        if (! Schema::hasColumn('orders', $field)) {
            $syncOrder->setAttribute($field, $value);
        }

        if (in_array($field, OrderTrackReceivedFields::specificFields(), true)) {
            $legacySync = OrderTrackReceivedFields::legacySyncAttributes($syncOrder, $field);
            $legacyFill = [];

            foreach ($legacySync as $legacyField => $legacyValue) {
                if (Schema::hasColumn('orders', $legacyField)) {
                    $legacyFill[$legacyField] = $legacyValue;
                }
            }

            if ($legacyFill !== []) {
                $syncOrder->forceFill($legacyFill)->save();
                $syncOrder = $syncOrder->fresh() ?? $syncOrder;
            }
        }

        if (in_array($field, ['customer_rate', 'carrier_rate', 'additional_expenses', 'insurance', 'bonus'], true)) {
            $this->financialTermsSyncService->syncFromOrderRates($syncOrder);
        }

        if (in_array($field, ['customer_payment_form', 'carrier_payment_form'], true)) {
            $this->financialTermsSyncService->syncFromOrderRates($syncOrder);
        }

        $trackReceivedFields = OrderTrackReceivedFields::allEditableFields();

        if (in_array($field, [
            'customer_rate',
            'carrier_rate',
            'additional_expenses',
            'insurance',
            'bonus',
            'customer_payment_form',
            'carrier_payment_form',
            'order_date',
            ...$trackReceivedFields,
        ], true)) {
            $this->orderCompensationService->recalculateImpactedPeriods(
                $syncOrder,
                null,
                $previousOrderDate,
            );
            $this->orderCompensationService->refreshOrderCompensationFields($syncOrder->fresh());
        }

        if (in_array($field, $trackReceivedFields, true)) {
            $orderForSchedule = $syncOrder->fresh();
            if ($orderForSchedule !== null) {
                $this->orderCompensationService->resyncPaymentSchedulesForOrder($orderForSchedule);
            }
        }

        if (in_array($field, [
            'customer_rate',
            'carrier_rate',
            'additional_expenses',
            'insurance',
            'bonus',
            'customer_payment_form',
            'carrier_payment_form',
        ], true)) {
            $this->orderWizardStateService->mergeInlineIntoOrder($order->fresh(), $field, $value);
        }

        return $order->fresh() ?? $syncOrder;
    }
}
