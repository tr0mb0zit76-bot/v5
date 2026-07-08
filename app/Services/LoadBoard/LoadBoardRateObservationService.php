<?php

namespace App\Services\LoadBoard;

use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\LoadBoardRateObservation;
use App\Support\LoadBoardOfferSource;
use Illuminate\Support\Collection;

class LoadBoardRateObservationService
{
    /**
     * @return array{abs: ?float, pct: ?float}
     */
    public function marginPreview(?float $customerRate, ?float $carrierRate): array
    {
        if ($customerRate === null || $carrierRate === null || $customerRate <= 0) {
            return ['abs' => null, 'pct' => null];
        }

        $abs = round($customerRate - $carrierRate, 2);

        return [
            'abs' => $abs,
            'pct' => round(($abs / $customerRate) * 100, 2),
        ];
    }

    public function recordOfferCreated(LoadBoardPost $post, LoadBoardOffer $offer): LoadBoardRateObservation
    {
        $post->refresh();
        $offer->refresh();

        return $this->storeObservation($post, $offer, 'open');
    }

    public function markOfferOutcome(LoadBoardOffer $offer, string $outcome): void
    {
        LoadBoardRateObservation::query()
            ->where('load_board_offer_id', $offer->id)
            ->update(['outcome' => $outcome]);
    }

    /**
     * @param  Collection<int, LoadBoardOffer>|iterable<int, LoadBoardOffer>  $offers
     */
    public function markOffersOutcome(iterable $offers, string $outcome, ?int $exceptOfferId = null): void
    {
        foreach ($offers as $offer) {
            if ($exceptOfferId !== null && (int) $offer->id === $exceptOfferId) {
                continue;
            }

            $this->markOfferOutcome($offer, $outcome);
        }
    }

    public function closeOpenObservationsForPost(LoadBoardPost $post, string $outcome = 'expired'): void
    {
        LoadBoardRateObservation::query()
            ->where('load_board_post_id', $post->id)
            ->where('outcome', 'open')
            ->update(['outcome' => $outcome]);
    }

    /**
     * @return array{
     *     available: bool,
     *     sample_size: int,
     *     carrier_rate: array{min: float|null, avg: float|null, max: float|null, currency: string},
     *     margin_pct: array{min: float|null, avg: float|null, max: float|null}
     * }|null
     */
    public function corridorInsightsForPost(LoadBoardPost $post): ?array
    {
        $corridorKey = LoadBoardCorridorKey::forPost($post);
        if ($corridorKey === null) {
            return null;
        }

        $rates = LoadBoardRateObservation::query()
            ->where('corridor_key', $corridorKey)
            ->whereIn('outcome', ['open', 'approved', 'not_selected'])
            ->whereNotNull('carrier_rate')
            ->orderByDesc('observed_at')
            ->limit(120)
            ->get(['carrier_rate', 'carrier_rate_currency', 'margin_pct']);

        if ($rates->isEmpty()) {
            return [
                'available' => false,
                'sample_size' => 0,
                'carrier_rate' => [
                    'min' => null,
                    'avg' => null,
                    'max' => null,
                    'currency' => $post->customer_rate_currency ?: 'RUB',
                ],
                'margin_pct' => [
                    'min' => null,
                    'avg' => null,
                    'max' => null,
                ],
            ];
        }

        $carrierValues = $rates->pluck('carrier_rate')->map(fn ($value): float => (float) $value);
        $marginValues = $rates->pluck('margin_pct')->filter(fn ($value): bool => $value !== null)->map(fn ($value): float => (float) $value);

        return [
            'available' => true,
            'sample_size' => $rates->count(),
            'carrier_rate' => [
                'min' => round((float) $carrierValues->min(), 2),
                'avg' => round((float) $carrierValues->avg(), 2),
                'max' => round((float) $carrierValues->max(), 2),
                'currency' => (string) ($rates->first()?->carrier_rate_currency ?: $post->customer_rate_currency ?: 'RUB'),
            ],
            'margin_pct' => [
                'min' => $marginValues->isNotEmpty() ? round((float) $marginValues->min(), 2) : null,
                'avg' => $marginValues->isNotEmpty() ? round((float) $marginValues->avg(), 2) : null,
                'max' => $marginValues->isNotEmpty() ? round((float) $marginValues->max(), 2) : null,
            ],
        ];
    }

    private function storeObservation(LoadBoardPost $post, LoadBoardOffer $offer, string $outcome): LoadBoardRateObservation
    {
        $customerRate = $post->customer_rate !== null ? (float) $post->customer_rate : null;
        $carrierRate = (float) $offer->carrier_rate;
        $margin = $this->marginPreview($customerRate, $carrierRate);
        $source = (string) ($offer->source ?: LoadBoardOfferSource::INTERNAL_CRM);

        return LoadBoardRateObservation::query()->updateOrCreate(
            ['load_board_offer_id' => $offer->id],
            [
                'load_board_post_id' => $post->id,
                'carrier_id' => $offer->carrier_id,
                'corridor_key' => LoadBoardCorridorKey::forPost($post),
                'loading_location' => $post->loading_location,
                'unloading_location' => $post->unloading_location,
                'truck_body_type_code' => $post->truck_body_type_code ?: $post->transport_type,
                'cargo_weight' => $post->cargo_weight,
                'customer_rate' => $customerRate,
                'customer_rate_currency' => strtoupper((string) ($post->customer_rate_currency ?: 'RUB')),
                'carrier_rate' => $carrierRate,
                'carrier_rate_currency' => strtoupper((string) ($offer->carrier_rate_currency ?: 'RUB')),
                'margin_abs' => $margin['abs'],
                'margin_pct' => $margin['pct'],
                'source' => $source,
                'outcome' => $outcome,
                'observed_at' => now(),
            ],
        );
    }
}
