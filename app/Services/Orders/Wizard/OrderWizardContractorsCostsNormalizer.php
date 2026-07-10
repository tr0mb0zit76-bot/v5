<?php

namespace App\Services\Orders\Wizard;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Support\ContractorCostRowClassification;
use App\Support\OwnFleetCatalog;

class OrderWizardContractorsCostsNormalizer
{
    /**
     * @param  list<array{stage: string|null, contractor_id: int|null}>  $serializedPerformers
     * @return list<array<string, mixed>>
     */
    public function normalize(Order $order, ?FinancialTerm $financialTerm, array $serializedPerformers = []): array
    {
        $savedCosts = $financialTerm?->contractors_costs ?? [];

        if (! is_array($savedCosts)) {
            $savedCosts = [];
        }

        if ($savedCosts !== []) {
            $contractorsCosts = collect($savedCosts)
                ->filter(fn (mixed $cost): bool => is_array($cost))
                ->values()
                ->map(function (array $cost) use ($financialTerm, $order): array {
                    $normalized = [
                        'stage' => $cost['stage'] ?? 'leg_1',
                        'carrier_slot' => isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                            ? (int) $cost['carrier_slot']
                            : null,
                        'contractor_id' => isset($cost['contractor_id']) && $cost['contractor_id'] !== null && $cost['contractor_id'] !== ''
                            ? (int) $cost['contractor_id']
                            : null,
                        'amount' => $cost['amount'] ?? null,
                        'currency' => $cost['currency'] ?? $financialTerm?->client_currency ?? 'RUB',
                        'payment_form' => $cost['payment_form'] ?? $order->carrier_payment_form ?? 'no_vat',
                        'payment_schedule' => is_array($cost['payment_schedule'] ?? null) ? $cost['payment_schedule'] : [],
                        'payment_terms' => $cost['payment_terms'] ?? '',
                        'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode($cost['execution_mode'] ?? null)
                            ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                            : null,
                        'is_additional' => ! empty($cost['is_additional']),
                        'incurred_date' => filled($cost['incurred_date'] ?? null)
                            ? substr((string) $cost['incurred_date'], 0, 10)
                            : null,
                    ];

                    if ($normalized['is_additional'] && ! ContractorCostRowClassification::isAdditionalStage((string) $normalized['stage'])) {
                        $normalized['stage'] = 'additional';
                    }

                    return $normalized;
                })
                ->all();

            $additionalIndex = 1;
            foreach ($contractorsCosts as &$costRow) {
                if (empty($costRow['is_additional'])) {
                    continue;
                }

                if (! ContractorCostRowClassification::isAdditionalStage((string) ($costRow['stage'] ?? ''))) {
                    $costRow['stage'] = "additional_{$additionalIndex}";
                    $additionalIndex++;
                }
            }
            unset($costRow);

            return $this->mergeOrderCarrierRateIntoContractorsCosts($contractorsCosts, $order->carrier_rate);
        }

        $contractorsCosts = collect($serializedPerformers)
            ->values()
            ->flatMap(function ($performer, int $index) use ($financialTerm, $order): array {
                if (! is_array($performer)) {
                    return [[
                        'stage' => 'leg_'.($index + 1),
                        'carrier_slot' => null,
                        'contractor_id' => null,
                        'amount' => null,
                        'currency' => $financialTerm?->client_currency ?? 'RUB',
                        'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                        'payment_schedule' => [],
                        'payment_terms' => '',
                    ]];
                }

                if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    return collect($performer['split_carriers'])
                        ->filter(fn (mixed $slot): bool => is_array($slot))
                        ->map(fn (array $slot): array => [
                            'stage' => $performer['stage'] ?? 'leg_1',
                            'carrier_slot' => (int) ($slot['slot'] ?? 1),
                            'contractor_id' => isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                                ? (int) $slot['contractor_id']
                                : null,
                            'amount' => null,
                            'currency' => $financialTerm?->client_currency ?? 'RUB',
                            'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                            'payment_schedule' => [],
                            'payment_terms' => '',
                        ])
                        ->all();
                }

                return [[
                    'stage' => $performer['stage'] ?? 'leg_'.($index + 1),
                    'carrier_slot' => null,
                    'contractor_id' => $performer['contractor_id'] ?? null,
                    'amount' => null,
                    'currency' => $financialTerm?->client_currency ?? 'RUB',
                    'payment_form' => $order->carrier_payment_form ?? 'no_vat',
                    'payment_schedule' => [],
                    'payment_terms' => '',
                ]];
            })
            ->all();

        return $this->mergeOrderCarrierRateIntoContractorsCosts($contractorsCosts, $order->carrier_rate);
    }

    /**
     * @param  list<array<string, mixed>>  $costs
     * @return list<array<string, mixed>>
     */
    public function mergeOrderCarrierRateIntoContractorsCosts(array $costs, mixed $carrierRate): array
    {
        if ($carrierRate === null || count($costs) === 0) {
            return $costs;
        }

        $legIndexes = [];
        foreach ($costs as $index => $cost) {
            if (! ContractorCostRowClassification::isAdditional($cost)) {
                $legIndexes[] = $index;
            }
        }

        if ($legIndexes === []) {
            return $costs;
        }

        $sum = collect($legIndexes)
            ->sum(fn (int $index): float => (float) ($costs[$index]['amount'] ?? 0));

        if (abs(round((float) $carrierRate, 2) - round($sum, 2)) < 0.01) {
            return $costs;
        }

        if (count($legIndexes) === 1) {
            $costs[$legIndexes[0]]['amount'] = (float) $carrierRate;

            return $costs;
        }

        $firstLegIndex = $legIndexes[0];
        $rest = collect($legIndexes)
            ->slice(1)
            ->sum(fn (int $index): float => (float) ($costs[$index]['amount'] ?? 0));
        $costs[$firstLegIndex]['amount'] = max(0, (float) $carrierRate - $rest);

        return $costs;
    }
}
