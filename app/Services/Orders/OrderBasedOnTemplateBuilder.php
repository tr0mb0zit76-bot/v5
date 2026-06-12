<?php

namespace App\Services\Orders;

use App\Models\Cargo;
use App\Models\Order;
use App\Models\RoutePoint;
use App\Support\RoutePointNormalizedData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrderBasedOnTemplateBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Order $order): array
    {
        $order->loadMissing([
            'client:id,name,inn,type',
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        $cargoItems = $this->orderCargoItems($order);

        return [
            'client_id' => $order->customer_id,
            'client_snapshot' => $order->relationLoaded('client') && $order->client !== null
                ? [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'inn' => $order->client->inn,
                    'type' => $order->client->type,
                ]
                : null,
            'own_company_id' => $order->own_company_id,
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'manual_status' => 'new',
            'loading_types' => $this->resolveLoadingTypes($order),
            'is_international_transport' => Schema::hasColumn('orders', 'is_international_transport')
                ? $order->isInternationalTransportEffective()
                : false,
            'route_points' => $this->mapRoutePoints($order),
            'cargo_items' => $cargoItems
                ->map(fn (Cargo $cargo): array => $this->mapCargoItem($cargo))
                ->values()
                ->all(),
            'performers' => [],
            'documents' => [],
            'financial_term' => [
                'client_price' => null,
                'client_currency' => 'RUB',
                'client_payment_form' => null,
                'client_payment_schedule' => [],
                'client_payment_terms' => '',
                'contractors_costs' => [],
                'additional_costs' => [],
                'kpi_percent' => 0,
                'client_norms_penalties' => [],
                'carrier_norms_by_leg' => [],
            ],
        ];
    }

    /**
     * @return Collection<int, Cargo>
     */
    private function orderCargoItems(Order $order): Collection
    {
        if (Schema::hasColumn('cargos', 'order_id')) {
            return $order->cargoItems()->orderBy('id')->get();
        }

        return Cargo::query()
            ->select('cargos.*')
            ->join('cargo_leg', 'cargo_leg.cargo_id', '=', 'cargos.id')
            ->join('order_legs', 'order_legs.id', '=', 'cargo_leg.order_leg_id')
            ->where('order_legs.order_id', $order->id)
            ->orderBy('cargos.id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapRoutePoints(Order $order): array
    {
        return $order->legs
            ->sortBy('sequence')
            ->flatMap(function ($leg) {
                return $leg->routePoints
                    ->sortBy('sequence')
                    ->map(fn (RoutePoint $point): array => [
                        'stage' => $this->normalizeStage((string) $leg->description),
                        'leg_sequence' => $leg->sequence,
                        'type' => $point->type,
                        'sequence' => $point->sequence,
                        'address' => filled($point->address)
                            ? $point->address
                            : data_get($point->metadata, 'address',
                                data_get($point->metadata, 'full_address',
                                    data_get($point->normalized_data, 'result', $point->instructions))),
                        'normalized_data' => RoutePointNormalizedData::resolveForWizard($point),
                        'planned_date' => optional($point->planned_date)?->toDateString(),
                        'planned_time_from' => filled($point->planned_time_from) ? substr((string) $point->planned_time_from, 0, 5) : null,
                        'planned_time_to' => filled($point->planned_time_to) ? substr((string) $point->planned_time_to, 0, 5) : null,
                        'actual_date' => null,
                        'actual_time' => null,
                        'contact_person' => $point->contact_person,
                        'contact_phone' => $point->contact_phone,
                        'sender_name' => $point->sender_name,
                        'sender_contact' => $point->sender_contact,
                        'sender_phone' => $point->sender_phone,
                        'recipient_name' => $point->recipient_name,
                        'recipient_contact' => $point->recipient_contact,
                        'recipient_phone' => $point->recipient_phone,
                    ]);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCargoItem(Cargo $cargo): array
    {
        return [
            'name' => $cargo->ati_cargo_name ?: $cargo->title,
            'description' => $cargo->description,
            'weight_value' => $cargo->weight_value ?? $cargo->weight,
            'weight_kg' => $cargo->weight_value ?? $cargo->weight,
            'weight_unit' => $cargo->weight_unit ?: 'kg',
            'volume_m3' => $cargo->volume,
            'length_m' => $cargo->length,
            'width_m' => $cargo->width,
            'height_m' => $cargo->height,
            'diameter_m' => $cargo->diameter,
            'package_type' => $cargo->packing_type,
            'pack_type_id' => $cargo->pack_type_id,
            'pack_type_label' => $cargo->pack_type_label,
            'loading_type_id' => $cargo->loading_type_id,
            'loading_type_code' => $cargo->loading_type_code,
            'loading_type_label' => $cargo->loading_type_label,
            'truck_body_type_id' => $cargo->truck_body_type_id,
            'truck_body_type_code' => $cargo->truck_body_type_code,
            'truck_body_type_label' => $cargo->truck_body_type_label,
            'trailer_type_id' => $cargo->trailer_type_id,
            'trailer_type_code' => $cargo->trailer_type_code,
            'trailer_type_label' => $cargo->trailer_type_label,
            'package_count' => $cargo->package_count ?? $cargo->pallet_count,
            'dangerous_goods' => $cargo->is_hazardous,
            'dangerous_class' => $cargo->hazard_class,
            'hs_code' => $cargo->hs_code,
            'cargo_type' => $cargo->cargo_type ?: 'general',
            'cargo_type_id' => $cargo->cargo_type_id,
            'cargo_type_label' => $cargo->cargo_type_label,
            'is_oversized' => $cargo->is_oversized,
            'is_fragile' => $cargo->is_fragile,
            'performer_allocations' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveLoadingTypes(Order $order): array
    {
        if (! Schema::hasColumn('orders', 'loading_types')) {
            return [];
        }

        $loadingTypes = $order->loading_types;
        if (is_string($loadingTypes)) {
            $decoded = json_decode($loadingTypes, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return is_array($loadingTypes) ? array_values($loadingTypes) : [];
    }

    private function normalizeStage(string $description): string
    {
        $trimmed = trim($description);

        if ($trimmed === '') {
            return 'leg_1';
        }

        if (preg_match('/^leg_\d+$/', $trimmed) === 1) {
            return $trimmed;
        }

        return 'leg_1';
    }
}
