<?php

namespace App\Services;

use App\Models\Order;
use App\Support\CargoPerformerAllocationBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class OrderWizardStateService
{
    /**
     * Сохраняет каноническое состояние мастера после успешного create/update заказа.
     *
     * @param  array<string, mixed>  $validated
     */
    public function persistFromValidated(Order $order, array $validated): void
    {
        if (! Schema::hasColumn('orders', 'wizard_state')) {
            return;
        }

        if (! filled($validated['financial_term'] ?? null)) {
            return;
        }

        $payload = [
            'version' => 1,
            'financial_term' => Arr::get($validated, 'financial_term', []),
            'performers' => Arr::get($validated, 'performers', []),
            'cargo_items' => $this->cargoItemsSnapshotForWizardState(
                Arr::get($validated, 'cargo_items', []),
                Arr::get($validated, 'performers', []),
            ),
            'print_form_template_selection' => Arr::get($validated, 'print_form_template_selection', []),
            'additional_expenses' => Arr::get($validated, 'additional_expenses'),
            'insurance' => Arr::get($validated, 'insurance'),
            'bonus' => Arr::get($validated, 'bonus'),
        ];

        $order->forceFill(['wizard_state' => $payload])->saveQuietly();
    }

    /**
     * После инлайн-редактирования в гриде синхронизируем JSON, чтобы карточка и грид сходились после F5.
     */
    public function mergeInlineIntoOrder(Order $order, string $field, mixed $value): void
    {
        if (! Schema::hasColumn('orders', 'wizard_state')) {
            return;
        }

        $state = $order->wizard_state;
        if (! is_array($state) || $state === []) {
            return;
        }

        match ($field) {
            'customer_rate' => $this->mergeClientPrice($state, $value),
            'carrier_rate' => $this->mergeCarrierRateIntoState($state, $value),
            'customer_payment_form' => data_set($state, 'financial_term.client_payment_form', $value),
            'carrier_payment_form' => $this->mergeCarrierPaymentFormIntoState($state, $value),
            'additional_expenses' => $state['additional_expenses'] = $value,
            'insurance' => $state['insurance'] = $value,
            'bonus' => $state['bonus'] = $value,
            default => null,
        };

        $order->forceFill(['wizard_state' => $state])->saveQuietly();
    }

    /**
     * @return list<array{name: string, performer_allocations: list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>}>
     */
    private function cargoItemsSnapshotForWizardState(mixed $cargoItems, mixed $performers): array
    {
        if (! is_array($cargoItems)) {
            return [];
        }

        $performerRows = is_array($performers)
            ? array_values(array_filter($performers, static fn (mixed $row): bool => is_array($row)))
            : [];

        $snapshot = [];

        foreach ($cargoItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $allocations = CargoPerformerAllocationBuilder::resolveForCargoItem($item, $performerRows);
            if ($allocations === []) {
                continue;
            }

            $snapshot[] = [
                'name' => $name,
                'performer_allocations' => $allocations,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function mergeClientPrice(array &$state, mixed $value): void
    {
        data_set($state, 'financial_term.client_price', $value);
    }

    /**
     * Логика согласована с {@see OrderWizardController::mergeOrderCarrierRateIntoContractorsCosts}.
     *
     * @param  array<string, mixed>  $state
     */
    private function mergeCarrierRateIntoState(array &$state, mixed $value): void
    {
        $costs = data_get($state, 'financial_term.contractors_costs');
        if (! is_array($costs) || $costs === []) {
            return;
        }

        if ($value === null) {
            return;
        }

        $carrierRate = (float) $value;
        $sum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));

        if (abs(round($carrierRate, 2) - round($sum, 2)) < 0.01) {
            return;
        }

        if (count($costs) === 1) {
            data_set($state, 'financial_term.contractors_costs.0.amount', $carrierRate);

            return;
        }

        $rest = collect($costs)->slice(1)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));
        data_set($state, 'financial_term.contractors_costs.0.amount', max(0.0, $carrierRate - $rest));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function mergeCarrierPaymentFormIntoState(array &$state, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $costs = data_get($state, 'financial_term.contractors_costs');
        if (! is_array($costs) || $costs === []) {
            return;
        }

        $form = (string) $value;
        foreach (array_keys($costs) as $i) {
            data_set($state, "financial_term.contractors_costs.{$i}.payment_form", $form);
        }
    }
}
