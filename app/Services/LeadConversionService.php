<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\Order;
use App\Models\User;
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

            $existingOrder = Schema::hasColumn('orders', 'lead_id')
                ? $lead->orders()->latest('id')->first()
                : null;

            if ($existingOrder !== null) {
                return $existingOrder;
            }

            $payload = [
                'status' => 'new',
                'own_company_id' => $ownCompanyId,
                'client_id' => $lead->counterparty_id,
                'order_date' => optional($lead->planned_shipping_date)->toDateString() ?? now()->toDateString(),
                'order_number' => null,
                'special_notes' => $lead->description,
                'performers' => [],
                'route_points' => $lead->routePoints->map(fn ($point): array => [
                    'type' => $point->type,
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
                'financial_term' => $lead->target_price === null ? null : [
                    'client_price' => $lead->target_price,
                    'client_currency' => $lead->target_currency ?: 'RUB',
                    'client_payment_schedule' => [],
                    'contractors_costs' => [],
                    'additional_costs' => [],
                    'kpi_percent' => null,
                ],
                'documents' => [],
            ];

            $order = $this->orderWizardService->create($payload, $user);

            if (Schema::hasColumn('orders', 'lead_id')) {
                $order->forceFill(['lead_id' => $lead->id])->save();
            }

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
}
