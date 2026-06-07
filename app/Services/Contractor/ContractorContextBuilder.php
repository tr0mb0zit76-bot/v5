<?php

namespace App\Services\Contractor;

use App\Models\Contractor;
use App\Support\ContractorPortraitDictionary;
use Illuminate\Support\Facades\Schema;

class ContractorContextBuilder
{
    public function __construct(
        private readonly ContractorPortraitService $portraitService,
        private readonly ContractorPortraitCoverage $coverage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Contractor $contractor, int $interactionLimit = 5): array
    {
        $contractor->loadMissing([
            'portrait',
            'contacts',
            'interactions' => fn ($query) => $query
                ->with(['author:id,name', 'contact:id,full_name,role_in_deal'])
                ->orderByDesc('contacted_at')
                ->limit($interactionLimit),
        ]);

        $portraitPayload = $this->portraitService->serializePortrait($contractor->portrait, $contractor);

        return [
            'portrait' => [
                'communication_style' => $portraitPayload['communication_style'],
                'communication_style_label' => ContractorPortraitDictionary::label(
                    'communication_style',
                    $portraitPayload['communication_style'],
                ),
                'price_sensitivity' => $portraitPayload['price_sensitivity'],
                'price_sensitivity_label' => ContractorPortraitDictionary::label(
                    'price_sensitivity',
                    $portraitPayload['price_sensitivity'],
                ),
                'preferred_channel' => $portraitPayload['preferred_channel'],
                'preferred_channel_label' => ContractorPortraitDictionary::label(
                    'preferred_channel',
                    $portraitPayload['preferred_channel'],
                ),
                'decision_cadence' => $portraitPayload['decision_cadence'],
                'decision_cadence_label' => ContractorPortraitDictionary::label(
                    'decision_cadence',
                    $portraitPayload['decision_cadence'],
                ),
                'relationship_trust' => $portraitPayload['relationship_trust'],
                'relationship_trust_label' => ContractorPortraitDictionary::label(
                    'relationship_trust',
                    $portraitPayload['relationship_trust'],
                ),
                'success_criteria' => $portraitPayload['success_criteria'],
                'typical_objections' => $portraitPayload['typical_objections'],
                'internal_notes' => $portraitPayload['internal_notes'],
                'coverage_pct' => $portraitPayload['coverage_pct'],
                'missing_slots' => $portraitPayload['missing_slots'],
                'assistant_ready' => $portraitPayload['coverage_pct'] >= ContractorPortraitCoverage::ASSISTANT_THRESHOLD,
            ],
            'contacts' => $contractor->contacts->map(fn ($contact): array => [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'position' => $contact->position,
                'role_in_deal' => $contact->role_in_deal,
                'role_in_deal_label' => ContractorPortraitDictionary::label('role_in_deal', $contact->role_in_deal),
                'communication_notes' => $contact->communication_notes,
                'is_decision_maker' => (bool) $contact->is_decision_maker,
            ])->values()->all(),
            'recent_interactions' => $contractor->interactions->map(fn ($interaction): array => [
                'id' => $interaction->id,
                'contacted_at' => optional($interaction->contacted_at)?->toIso8601String(),
                'channel' => $interaction->channel,
                'outcome_code' => $interaction->outcome_code,
                'outcome_label' => ContractorPortraitDictionary::label('outcome_code', $interaction->outcome_code),
                'summary' => $interaction->summary,
                'contact_name' => $interaction->contact?->full_name,
                'objection_tags' => is_array($interaction->objection_tags) ? $interaction->objection_tags : [],
            ])->values()->all(),
            'open_leads_count' => Schema::hasTable('leads')
                ? $contractor->leadsAsCounterparty()->whereNotIn('status', ['won', 'lost'])->count()
                : 0,
        ];
    }
}
