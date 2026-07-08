<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Support\LoadBoardOfferSource;

class LoadBoardPostPresenter
{
    public function __construct(
        private readonly LoadBoardRateObservationService $rateObservations,
        private readonly ProcurementCasePresenter $procurementCases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(LoadBoardPost $post): array
    {
        return [
            'id' => $post->id,
            'lead_id' => $post->lead_id,
            'order_id' => $post->order_id,
            'customer_id' => $post->customer_id,
            'seller_id' => $post->seller_id,
            'buyer_id' => $post->buyer_id,
            'accepted_offer_id' => $post->accepted_offer_id,
            'accepted_by' => $post->accepted_by,
            'accepted_at' => $post->accepted_at?->toDateTimeString(),
            'status' => $post->status,
            'priority' => $post->priority,
            'title' => $post->title,
            'loading_location' => $post->loading_location,
            'unloading_location' => $post->unloading_location,
            'loading_date' => $post->loading_date?->toDateString(),
            'unloading_date' => $post->unloading_date?->toDateString(),
            'cargo_name' => $post->cargo_name,
            'ati_cargo_name' => $post->ati_cargo_name,
            'cargo_weight' => $post->cargo_weight,
            'cargo_volume' => $post->cargo_volume,
            'cargo_type_id' => $post->cargo_type_id,
            'cargo_type' => $post->cargo_type,
            'cargo_type_label' => $post->cargo_type_label,
            'pack_type_id' => $post->pack_type_id,
            'package_type' => $post->package_type,
            'pack_type_label' => $post->pack_type_label,
            'package_count' => $post->package_count,
            'loading_type_id' => $post->loading_type_id,
            'loading_type_code' => $post->loading_type_code,
            'loading_type_label' => $post->loading_type_label,
            'loading_type_items' => $post->loading_type_items ?? [],
            'truck_body_type_id' => $post->truck_body_type_id,
            'truck_body_type_code' => $post->truck_body_type_code,
            'truck_body_type_label' => $post->truck_body_type_label,
            'truck_body_type_items' => $post->truck_body_type_items ?? [],
            'trailer_type_id' => $post->trailer_type_id,
            'trailer_type_code' => $post->trailer_type_code,
            'trailer_type_label' => $post->trailer_type_label,
            'trailer_type_items' => $post->trailer_type_items ?? [],
            'length' => $post->length,
            'width' => $post->width,
            'height' => $post->height,
            'diameter' => $post->diameter,
            'is_hazardous' => $post->is_hazardous,
            'hazard_class' => $post->hazard_class,
            'needs_temperature' => $post->needs_temperature,
            'temp_min' => $post->temp_min,
            'temp_max' => $post->temp_max,
            'is_oversized' => $post->is_oversized,
            'is_fragile' => $post->is_fragile,
            'hs_code' => $post->hs_code,
            'ati_cargo_payload' => $post->ati_cargo_payload ?? [],
            'transport_type' => $post->transport_type,
            'customer_rate' => $post->customer_rate,
            'customer_rate_currency' => $post->customer_rate_currency,
            'target_carrier_rate' => $post->target_carrier_rate,
            'payment_form' => $post->payment_form,
            'requirements' => $post->requirements,
            'seller_comment' => $post->seller_comment,
            'metadata' => $post->metadata ?? [],
            'published_at' => $post->published_at?->toDateTimeString(),
            'taken_at' => $post->taken_at?->toDateTimeString(),
            'closed_at' => $post->closed_at?->toDateTimeString(),
            'updated_at' => $post->updated_at?->toDateTimeString(),
            'seller' => $post->seller?->only(['id', 'name']),
            'buyer' => $post->buyer?->only(['id', 'name']),
            'accepted_offer' => $post->acceptedOffer?->only(['id', 'carrier_id', 'carrier_rate', 'carrier_rate_currency', 'payment_form']),
            'accepter' => $post->accepter?->only(['id', 'name']),
            'customer' => $post->customer?->only(['id', 'name']),
            'lead' => $post->lead?->only(['id', 'number', 'title']),
            'order' => $post->order?->only(['id', 'order_number']),
            'procurement_case' => $this->procurementCases->present($post->procurementCase),
            'offers_count' => $post->offers_count,
            'offers_summary' => $this->offersSummary($post, $post->offers),
            'offers' => $post->offers
                ->sortByDesc(fn (LoadBoardOffer $offer): int => match ($offer->status) {
                    'approved' => 3,
                    'selected' => 2,
                    default => 1,
                })
                ->map(function (LoadBoardOffer $offer) use ($post): array {
                    $margin = $this->rateObservations->marginPreview(
                        $post->customer_rate !== null ? (float) $post->customer_rate : null,
                        (float) $offer->carrier_rate,
                    );

                    return [
                        'id' => $offer->id,
                        'carrier_id' => $offer->carrier_id,
                        'status' => $offer->status,
                        'source' => $offer->source ?: LoadBoardOfferSource::INTERNAL_CRM,
                        'source_label' => LoadBoardOfferSource::label($offer->source),
                        'carrier_rate' => $offer->carrier_rate,
                        'carrier_rate_currency' => $offer->carrier_rate_currency,
                        'margin_abs' => $margin['abs'],
                        'margin_pct' => $margin['pct'],
                        'payment_form' => $offer->payment_form,
                        'available_date' => $offer->available_date?->toDateString(),
                        'carrier_contact' => $offer->carrier_contact,
                        'conditions' => $offer->conditions,
                        'comment' => $offer->comment,
                        'selected_at' => $offer->selected_at?->toDateTimeString(),
                        'carrier' => $offer->carrier?->only(['id', 'name']),
                        'creator' => $offer->creator?->only(['id', 'name']),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  iterable<int, LoadBoardPost>  $posts
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $posts): array
    {
        $presented = [];

        foreach ($posts as $post) {
            $presented[] = $this->present($post);
        }

        return $presented;
    }

    /**
     * @param  iterable<int, LoadBoardOffer>  $offers
     * @return array{
     *     best_rate: float|null,
     *     best_margin_abs: float|null,
     *     best_margin_pct: float|null,
     *     sources: list<string>,
     *     sources_label: string
     * }
     */
    private function offersSummary(LoadBoardPost $post, iterable $offers): array
    {
        $customerRate = $post->customer_rate !== null ? (float) $post->customer_rate : null;
        $bestRate = null;
        $sources = [];

        foreach ($offers as $offer) {
            $sources[] = LoadBoardOfferSource::label($offer->source);
            $rate = (float) $offer->carrier_rate;

            if ($bestRate === null || $rate < $bestRate) {
                $bestRate = $rate;
            }
        }

        $sources = array_values(array_unique(array_filter($sources)));

        if ($bestRate === null) {
            return [
                'best_rate' => null,
                'best_margin_abs' => null,
                'best_margin_pct' => null,
                'sources' => [],
                'sources_label' => '—',
            ];
        }

        $margin = $this->rateObservations->marginPreview($customerRate, $bestRate);

        return [
            'best_rate' => $bestRate,
            'best_margin_abs' => $margin['abs'],
            'best_margin_pct' => $margin['pct'],
            'sources' => $sources,
            'sources_label' => $sources !== [] ? implode(', ', $sources) : '—',
        ];
    }
}
