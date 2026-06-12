<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Support\LeadCargoItemPayloadNormalizer;
use App\Support\LeadRoutePointPayloadNormalizer;

class LeadBasedOnTemplateBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Lead $lead): array
    {
        $lead->loadMissing(['cargoItems', 'routePoints', 'counterparty:id,name']);

        return [
            'status' => 'new',
            'source' => $lead->source,
            'counterparty_id' => $lead->counterparty_id,
            'title' => $lead->title,
            'description' => $lead->description,
            'transport_type' => $lead->transport_type,
            'loading_location' => $lead->loading_location,
            'unloading_location' => $lead->unloading_location,
            'planned_shipping_date' => optional($lead->planned_shipping_date)?->toDateString(),
            'target_price' => $lead->target_price,
            'target_currency' => $lead->target_currency ?: 'RUB',
            'qualification' => is_array($lead->lead_qualification) ? $lead->lead_qualification : [],
            'route_points' => $lead->routePoints
                ->map(fn ($point): array => LeadRoutePointPayloadNormalizer::toFrontend($point))
                ->values()
                ->all(),
            'cargo_items' => $lead->cargoItems
                ->map(fn ($cargo): array => LeadCargoItemPayloadNormalizer::toFrontend($cargo))
                ->values()
                ->all(),
        ];
    }
}
