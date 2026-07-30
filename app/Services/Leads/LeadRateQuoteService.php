<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadRateQuote;
use App\Models\User;
use App\Support\LeadPerformerPayloadNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadRateQuoteService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('lead_rate_quotes');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForLead(Lead $lead): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return $lead->rateQuotes()
            ->with(['contractor:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (LeadRateQuote $quote): array => $this->serialize($quote))
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     contractor_id?: int|null,
     *     carrier_name?: string|null,
     *     rate: float|int|string,
     *     currency?: string|null,
     *     payment_form?: string|null,
     *     valid_until?: string|null,
     *     source?: string|null,
     *     comment?: string|null,
     *     load_board_offer_id?: int|null
     * }  $payload
     */
    public function store(Lead $lead, array $payload, ?User $actor = null): LeadRateQuote
    {
        $carrierName = isset($payload['carrier_name']) ? trim((string) $payload['carrier_name']) : null;
        if ($carrierName === '') {
            $carrierName = null;
        }

        return $lead->rateQuotes()->create([
            'contractor_id' => $payload['contractor_id'] ?? null,
            'load_board_offer_id' => $payload['load_board_offer_id'] ?? null,
            'created_by' => $actor?->id,
            'carrier_name' => $carrierName,
            'rate' => $payload['rate'],
            'currency' => strtoupper((string) ($payload['currency'] ?? 'RUB')),
            'payment_form' => $payload['payment_form'] ?? null,
            'valid_until' => $payload['valid_until'] ?? null,
            'source' => $payload['source'] ?? LeadRateQuote::SOURCE_MANUAL,
            'status' => LeadRateQuote::STATUS_RECEIVED,
            'comment' => $payload['comment'] ?? null,
        ]);
    }

    public function select(Lead $lead, LeadRateQuote $quote): LeadRateQuote
    {
        abort_unless($quote->lead_id === $lead->id, 404);

        return DB::transaction(function () use ($lead, $quote): LeadRateQuote {
            $lead->rateQuotes()
                ->where('id', '!=', $quote->id)
                ->where('status', '!=', LeadRateQuote::STATUS_EXPIRED)
                ->update([
                    'status' => LeadRateQuote::STATUS_REJECTED,
                    'selected_at' => null,
                ]);

            $quote->forceFill([
                'status' => LeadRateQuote::STATUS_SELECTED,
                'selected_at' => now(),
            ])->save();

            $this->applySelectedQuoteToLead($lead, $quote->fresh());

            return $quote->fresh(['contractor:id,name']) ?? $quote;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(LeadRateQuote $quote): array
    {
        $carrierLabel = $quote->contractor?->name
            ?: ($quote->carrier_name ?: 'Без названия');

        return [
            'id' => $quote->id,
            'contractor_id' => $quote->contractor_id,
            'carrier_name' => $quote->carrier_name,
            'carrier_label' => $carrierLabel,
            'rate' => $quote->rate,
            'currency' => $quote->currency,
            'payment_form' => $quote->payment_form,
            'valid_until' => optional($quote->valid_until)?->toDateString(),
            'source' => $quote->source,
            'status' => $quote->status,
            'comment' => $quote->comment,
            'load_board_offer_id' => $quote->load_board_offer_id,
            'selected_at' => optional($quote->selected_at)?->toIso8601String(),
            'created_at' => optional($quote->created_at)?->toIso8601String(),
        ];
    }

    private function applySelectedQuoteToLead(Lead $lead, LeadRateQuote $quote): void
    {
        $performers = LeadPerformerPayloadNormalizer::normalizeList(
            is_array($lead->performers) ? $lead->performers : null,
        );

        $first = $performers[0];
        $first['estimated_cost'] = $quote->rate;
        if ($quote->contractor_id !== null) {
            $first['contractor_id'] = $quote->contractor_id;
            $first['contractor_name'] = $quote->contractor?->name ?? $first['contractor_name'];
        } elseif (filled($quote->carrier_name)) {
            $first['contractor_name'] = $quote->carrier_name;
        }
        $performers[0] = $first;

        $lead->forceFill([
            'calculated_cost' => $quote->rate,
            'carrier_payment_form' => $quote->payment_form ?: $lead->carrier_payment_form,
            'performers' => $performers,
            'updated_by' => $quote->created_by,
        ])->save();
    }
}
