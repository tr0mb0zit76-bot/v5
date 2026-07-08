<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\Order;
use App\Models\User;
use App\Support\LeadPerformerPayloadNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadConversionService
{
    public function __construct(
        private readonly OrderWizardService $orderWizardService,
    ) {}

    public function convert(Lead $lead, User $user, ?int $ownCompanyId = null): Order
    {
        return DB::transaction(function () use ($lead, $user, $ownCompanyId): Order {
            $lead->loadMissing('cargoItems', 'routePoints', 'offers');

            $existingOrder = $lead->orders()->latest('id')->first();

            if ($existingOrder !== null) {
                return $existingOrder;
            }

            $performers = $this->orderPerformersFromLead($lead);

            $payload = [
                'status' => 'new',
                'own_company_id' => $ownCompanyId,
                'client_id' => $lead->counterparty_id,
                'order_date' => optional($lead->planned_shipping_date)->toDateString() ?? now()->toDateString(),
                'order_number' => null,
                'special_notes' => $lead->description,
                'performers' => $performers,
                'route_points' => $lead->routePoints->map(fn ($point): array => [
                    'type' => $point->type,
                    'stage' => (string) ($point->stage ?? 'leg_1'),
                    'sequence' => $point->sequence,
                    'address' => $point->address,
                    'normalized_data' => $point->normalized_data ?? [],
                    'planned_date' => optional($point->planned_date)->toDateString(),
                    'actual_date' => null,
                    'contact_person' => $point->contact_person,
                    'contact_phone' => $point->contact_phone,
                ])->values()->all(),
                'cargo_items' => $lead->cargoItems->map(fn ($cargo): array => [
                    'name' => $cargo->name,
                    'description' => $cargo->description,
                    'weight_kg' => $cargo->weight_kg,
                    'volume_m3' => $cargo->volume_m3,
                    'package_type' => $cargo->package_type,
                    'package_count' => $cargo->package_count,
                    'dangerous_goods' => (bool) $cargo->dangerous_goods,
                    'dangerous_class' => $cargo->dangerous_class,
                    'hs_code' => $cargo->hs_code,
                    'cargo_type' => $cargo->cargo_type,
                ])->values()->all(),
                'financial_term' => $this->buildFinancialTermFromLead($lead),
                'documents' => [],
            ];

            $order = $this->orderWizardService->create($payload, $user);

            $order->forceFill(['lead_id' => $lead->id])->save();
            $this->attachLeadPrecalculationSnapshot($order, $lead);

            $lead->forceFill([
                'status' => 'won',
                'updated_by' => $user->id,
                'metadata' => array_merge($lead->metadata ?? [], ['converted_order_id' => $order->id]),
            ])->save();

            $lead->activities()->create([
                'type' => 'status_change',
                'subject' => 'Конвертация в заказ',
                'content' => 'Лид конвертирован в заказ #'.$order->order_number,
                'created_by' => $user->id,
            ]);

            if (! $lead->offers()->exists()) {
                LeadOffer::query()->create([
                    'lead_id' => $lead->id,
                    'status' => 'prepared',
                    'number' => 'КП-'.$lead->number,
                    'offer_date' => now()->toDateString(),
                    'price' => $lead->target_price,
                    'currency' => $lead->target_currency ?: 'RUB',
                    'payload' => [
                        'title' => $lead->title,
                        'description' => $lead->description,
                        'route' => [
                            'loading_location' => $lead->loading_location,
                            'unloading_location' => $lead->unloading_location,
                        ],
                    ],
                    'created_by' => $user->id,
                ]);
            }

            return $order;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderPerformersFromLead(Lead $lead): array
    {
        $performers = LeadPerformerPayloadNormalizer::normalizeList(
            is_array($lead->performers) ? $lead->performers : null,
        );

        return collect($performers)
            ->map(fn (array $performer): array => [
                'stage' => $performer['stage'],
                'carrier_mode' => 'single',
                'contractor_id' => $performer['contractor_id'],
                'contractor_name' => $performer['contractor_name'],
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
                'execution_mode' => null,
                'fleet_trip_id' => null,
                'loading_actual' => '',
                'unloading_actual' => '',
                'loading_special_conditions' => '',
                'unloading_special_conditions' => '',
                'split_carriers' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildFinancialTermFromLead(Lead $lead): ?array
    {
        $performers = LeadPerformerPayloadNormalizer::normalizeList(
            is_array($lead->performers) ? $lead->performers : null,
        );

        $currency = $lead->target_currency ?: 'RUB';
        $contractorsCosts = [];

        foreach ($performers as $performer) {
            if ($performer['estimated_cost'] === null) {
                continue;
            }

            $contractorsCosts[] = [
                'stage' => $performer['stage'],
                'contractor_id' => $performer['contractor_id'],
                'amount' => $performer['estimated_cost'],
                'currency' => $currency,
                'payment_form' => $lead->carrier_payment_form ?: 'no_vat',
                'payment_schedule' => [],
            ];
        }

        if ($contractorsCosts === [] && $lead->calculated_cost !== null) {
            $contractorsCosts[] = [
                'stage' => 'leg_1',
                'contractor_id' => null,
                'amount' => $lead->calculated_cost,
                'currency' => $currency,
                'payment_form' => $lead->carrier_payment_form ?: 'no_vat',
                'payment_schedule' => [],
            ];
        }

        if ($lead->target_price === null && $contractorsCosts === []) {
            return null;
        }

        return [
            'client_price' => $lead->target_price,
            'client_currency' => $currency,
            'client_payment_form' => $lead->customer_payment_form,
            'client_payment_schedule' => [],
            'contractors_costs' => $contractorsCosts,
            'additional_costs' => [],
            'kpi_percent' => null,
        ];
    }

    private function attachLeadPrecalculationSnapshot(Order $order, Lead $lead): void
    {
        if (! is_array($lead->precalculation) || $lead->precalculation === []) {
            return;
        }

        $snapshot = [
            'lead_precalculation_snapshot' => $lead->precalculation,
            'lead_precalculation_snapshot_at' => now()->toIso8601String(),
        ];

        if (Schema::hasColumn('orders', 'metadata')) {
            $metadata = is_array($order->metadata) ? $order->metadata : [];
            $order->forceFill([
                'metadata' => array_merge($metadata, $snapshot),
            ])->saveQuietly();

            return;
        }

        if (Schema::hasColumn('orders', 'wizard_state')) {
            $wizardState = is_array($order->wizard_state) ? $order->wizard_state : [];
            $order->forceFill([
                'wizard_state' => array_merge($wizardState, $snapshot),
            ])->saveQuietly();
        }
    }
}
