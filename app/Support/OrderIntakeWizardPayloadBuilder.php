<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Собирает payload мастера заказа из wizard_patch черновика заявки.
 */
final class OrderIntakeWizardPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public static function fromWizardPatch(array $patch, User $user): array
    {
        $patch = OrderIntakeSchema::sanitizeWizardPatch($patch);

        $routePoints = self::normalizeRoutePoints($patch['route_points'] ?? []);
        $financialTerm = self::normalizeFinancialTerm($patch);
        $performers = self::normalizePerformers($patch);

        $payload = array_filter([
            'status' => 'new',
            'client_id' => isset($patch['client_id']) ? (int) $patch['client_id'] : null,
            'own_company_id' => isset($patch['own_company_id']) ? (int) $patch['own_company_id'] : null,
            'order_date' => $patch['order_date'] ?? now()->toDateString(),
            'order_number' => $patch['order_number'] ?? null,
            'order_customer_number' => $patch['order_customer_number'] ?? null,
            'loading_date' => $patch['loading_date'] ?? null,
            'unloading_date' => $patch['unloading_date'] ?? null,
            'customer_contact_name' => $patch['customer_contact_name'] ?? null,
            'customer_contact_phone' => $patch['customer_contact_phone'] ?? null,
            'customer_contact_email' => $patch['customer_contact_email'] ?? null,
            'cargo_sender_name' => $patch['cargo_sender_name'] ?? null,
            'cargo_sender_phone' => $patch['cargo_sender_phone'] ?? null,
            'cargo_recipient_name' => $patch['cargo_recipient_name'] ?? null,
            'cargo_recipient_phone' => $patch['cargo_recipient_phone'] ?? null,
            'special_notes' => $patch['special_notes'] ?? null,
            'manager_id' => $user->id,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $payload['route_points'] = $routePoints;
        $payload['performers'] = $performers;
        $payload['cargo_items'] = self::normalizeCargoItems($patch);
        $payload['financial_term'] = $financialTerm;
        $payload['documents'] = [];

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeRoutePoints(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        return collect($points)
            ->filter(fn (mixed $point): bool => is_array($point))
            ->values()
            ->map(function (array $point, int $index): array {
                return array_filter([
                    'type' => $point['type'] ?? ($index === 0 ? 'loading' : 'unloading'),
                    'sequence' => (int) ($point['sequence'] ?? ($index + 1)),
                    'stage' => PerformerRouteActualDates::normalizeStageKey((string) ($point['stage'] ?? 'leg_1')),
                    'address' => filled($point['address'] ?? null) ? (string) $point['address'] : null,
                    'planned_date' => filled($point['planned_date'] ?? null) ? (string) $point['planned_date'] : null,
                    'contact_person' => $point['contact_person'] ?? null,
                    'contact_phone' => $point['contact_phone'] ?? null,
                    'normalized_data' => is_array($point['normalized_data'] ?? null) ? $point['normalized_data'] : [],
                ], fn (mixed $value): bool => $value !== null && $value !== '');
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return list<array<string, mixed>>
     */
    private static function normalizePerformers(array $patch): array
    {
        $carrierId = isset($patch['carrier_contractor_id']) ? (int) $patch['carrier_contractor_id'] : null;

        return [[
            'stage' => 'leg_1',
            'carrier_mode' => 'single',
            'contractor_id' => $carrierId > 0 ? $carrierId : null,
            'contractor_name' => $patch['carrier_contractor_name'] ?? null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return list<array<string, mixed>>
     */
    private static function normalizeCargoItems(array $patch): array
    {
        $items = $patch['cargo_items'] ?? null;

        if (! is_array($items) || ! isset($items[0]) || ! is_array($items[0])) {
            return [[
                'name' => '',
                'description' => null,
                'weight_kg' => null,
                'volume_m3' => null,
                'package_count' => null,
            ]];
        }

        $item = $items[0];

        return [[
            'name' => (string) ($item['name'] ?? ''),
            'description' => $item['description'] ?? null,
            'weight_kg' => $item['weight_kg'] ?? null,
            'volume_m3' => $item['volume_m3'] ?? null,
            'package_count' => $item['package_count'] ?? null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private static function normalizeFinancialTerm(array $patch): array
    {
        $source = is_array($patch['financial_term'] ?? null) ? $patch['financial_term'] : [];

        $clientPrice = Arr::get($source, 'client_price');
        $contractorsCosts = Arr::get($source, 'contractors_costs', []);

        if (! is_array($contractorsCosts) || $contractorsCosts === []) {
            $carrierId = isset($patch['carrier_contractor_id']) ? (int) $patch['carrier_contractor_id'] : null;
            $amount = Arr::get($source, 'carrier_rate') ?? Arr::get($source, 'amount');

            if ($amount !== null && $amount !== '') {
                $row = [
                    'stage' => 'leg_1',
                    'amount' => round((float) $amount, 2),
                    'payment_terms' => Arr::get($source, 'carrier_payment_terms'),
                ];

                if ($carrierId > 0) {
                    $row['contractor_id'] = $carrierId;
                }

                $contractorsCosts = [$row];
            }
        }

        return array_filter([
            'client_price' => $clientPrice !== null && $clientPrice !== '' ? round((float) $clientPrice, 2) : null,
            'client_currency' => (string) ($source['client_currency'] ?? 'RUB'),
            'client_payment_form' => (string) ($source['client_payment_form'] ?? 'vat'),
            'client_payment_terms' => $source['client_payment_terms'] ?? null,
            'contractors_costs' => is_array($contractorsCosts) ? $contractorsCosts : [],
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
