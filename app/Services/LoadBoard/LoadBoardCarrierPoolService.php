<?php

declare(strict_types=1);

namespace App\Services\LoadBoard;

use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Support\LoadBoardOfferSource;
use Illuminate\Support\Collection;

final class LoadBoardCarrierPoolService
{
    /**
     * @return array{
     *     total: int,
     *     entries: list<array<string, mixed>>,
     *     sources_summary: list<array{source: string, label: string, count: int}>
     * }
     */
    public function forPost(LoadBoardPost $post): array
    {
        /** @var Collection<int, LoadBoardOffer> $offers */
        $offers = $post->relationLoaded('offers')
            ? $post->offers
            : $post->offers()->with('carrier:id,name')->get();

        $entries = [];
        $bestByKey = [];

        foreach ($offers as $offer) {
            $key = $this->dedupKey($offer);
            $rate = (float) $offer->carrier_rate;

            if (isset($bestByKey[$key]) && (float) ($bestByKey[$key]['carrier_rate'] ?? PHP_FLOAT_MAX) <= $rate) {
                continue;
            }

            $entry = $this->entryFromOffer($offer);
            $bestByKey[$key] = $entry;
        }

        $entries = array_values($bestByKey);
        usort($entries, fn (array $left, array $right): int => (float) $left['carrier_rate'] <=> (float) $right['carrier_rate']);

        $metadata = is_array($post->metadata) ? $post->metadata : [];
        foreach ($metadata['carrier_pool_candidates'] ?? [] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $key = $this->dedupKeyFromCandidate($candidate);
            if (isset($bestByKey[$key])) {
                continue;
            }

            $entries[] = $this->entryFromCandidate($candidate);
        }

        usort($entries, fn (array $left, array $right): int => (float) ($left['carrier_rate'] ?? PHP_FLOAT_MAX) <=> (float) ($right['carrier_rate'] ?? PHP_FLOAT_MAX));

        return [
            'total' => count($entries),
            'entries' => $entries,
            'sources_summary' => $this->sourcesSummary($entries),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function hasEntryForCandidate(LoadBoardPost $post, array $candidate): bool
    {
        $key = $this->dedupKeyFromCandidate($candidate);

        $post->loadMissing(['offers.carrier']);

        foreach ($post->offers as $offer) {
            if ($this->dedupKey($offer) === $key) {
                return true;
            }
        }

        $metadata = is_array($post->metadata) ? $post->metadata : [];
        foreach ($metadata['carrier_pool_candidates'] ?? [] as $existing) {
            if (! is_array($existing)) {
                continue;
            }

            if ($this->dedupKeyFromCandidate($existing) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFromOffer(LoadBoardOffer $offer): array
    {
        $source = (string) ($offer->source ?: LoadBoardOfferSource::INTERNAL_CRM);

        return [
            'pool_key' => $this->dedupKey($offer),
            'offer_id' => $offer->id,
            'carrier_id' => $offer->carrier_id,
            'carrier_name' => $offer->carrier?->name,
            'source' => $source,
            'source_label' => LoadBoardOfferSource::label($source),
            'carrier_rate' => (float) $offer->carrier_rate,
            'carrier_rate_currency' => strtoupper((string) ($offer->carrier_rate_currency ?: 'RUB')),
            'status' => $offer->status,
            'carrier_contact' => $offer->carrier_contact,
            'conditions' => $offer->conditions,
            'comment' => $offer->comment,
            'kind' => 'offer',
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function entryFromCandidate(array $candidate): array
    {
        $source = (string) ($candidate['source'] ?? LoadBoardOfferSource::PHONE);
        $carrierId = isset($candidate['carrier_id']) ? (int) $candidate['carrier_id'] : null;

        return [
            'pool_key' => $this->dedupKeyFromCandidate($candidate),
            'candidate_id' => $candidate['id'] ?? null,
            'offer_id' => null,
            'carrier_id' => $carrierId,
            'carrier_name' => $candidate['carrier_name'] ?? null,
            'source' => $source,
            'source_label' => LoadBoardOfferSource::label($source),
            'carrier_rate' => isset($candidate['carrier_rate']) ? (float) $candidate['carrier_rate'] : null,
            'carrier_rate_currency' => strtoupper((string) ($candidate['carrier_rate_currency'] ?? 'RUB')),
            'status' => 'candidate',
            'carrier_contact' => $candidate['carrier_contact'] ?? null,
            'conditions' => $candidate['conditions'] ?? null,
            'comment' => $candidate['comment'] ?? null,
            'kind' => 'candidate',
        ];
    }

    private function dedupKey(LoadBoardOffer $offer): string
    {
        $source = (string) ($offer->source ?: LoadBoardOfferSource::INTERNAL_CRM);

        if ($offer->carrier_id !== null) {
            return 'carrier:'.$offer->carrier_id.':source:'.$source;
        }

        $contact = mb_strtolower(trim((string) ($offer->carrier_contact ?? '')));
        $name = mb_strtolower(trim((string) ($offer->carrier?->name ?? '')));

        return 'contact:'.sha1($source.'|'.$contact.'|'.$name);
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function dedupKeyFromCandidate(array $candidate): string
    {
        $source = (string) ($candidate['source'] ?? LoadBoardOfferSource::PHONE);
        $carrierId = isset($candidate['carrier_id']) ? (int) $candidate['carrier_id'] : null;

        if ($carrierId !== null && $carrierId > 0) {
            return 'carrier:'.$carrierId.':source:'.$source;
        }

        $contact = mb_strtolower(trim((string) ($candidate['carrier_contact'] ?? '')));
        $name = mb_strtolower(trim((string) ($candidate['carrier_name'] ?? '')));

        return 'contact:'.sha1($source.'|'.$contact.'|'.$name);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array{source: string, label: string, count: int}>
     */
    private function sourcesSummary(array $entries): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $source = (string) ($entry['source'] ?? LoadBoardOfferSource::INTERNAL_CRM);
            $counts[$source] = ($counts[$source] ?? 0) + 1;
        }

        $summary = [];
        foreach ($counts as $source => $count) {
            $summary[] = [
                'source' => $source,
                'label' => LoadBoardOfferSource::label($source),
                'count' => $count,
            ];
        }

        usort($summary, fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return $summary;
    }
}
