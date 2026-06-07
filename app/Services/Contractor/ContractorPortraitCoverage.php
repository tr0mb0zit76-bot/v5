<?php

namespace App\Services\Contractor;

use App\Models\Contractor;
use App\Support\ContractorPortraitDictionary;

class ContractorPortraitCoverage
{
    public const ASSISTANT_THRESHOLD = 60;

    /**
     * @return list<string>
     */
    public function missingSlots(Contractor $contractor): array
    {
        $missing = [];
        $portrait = $contractor->portrait;
        $contacts = $contractor->relationLoaded('contacts')
            ? $contractor->contacts
            : $contractor->contacts()->get();

        $hasRoleContact = $contacts->contains(
            fn ($contact): bool => filled($contact->role_in_deal)
                && $contact->role_in_deal !== ContractorPortraitDictionary::UNKNOWN
        ) || $contacts->contains(fn ($contact): bool => (bool) $contact->is_decision_maker);

        if (! $hasRoleContact) {
            $missing[] = 'ЛПР или роль в сделке в карте контактов';
        }

        if (($portrait?->communication_style ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN) {
            $missing[] = 'Стиль общения';
        }

        if (($portrait?->preferred_channel ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN) {
            $missing[] = 'Предпочитаемый канал';
        }

        if (blank($portrait?->success_criteria)) {
            $missing[] = 'Критерии успеха перевозки';
        }

        $objections = is_array($portrait?->typical_objections) ? $portrait->typical_objections : [];
        $hasRecentObjectionInteraction = $contractor->interactions()
            ->where('contacted_at', '>=', now()->subDays(180))
            ->where(function ($query): void {
                $query->where('outcome_code', 'objection')
                    ->orWhereNotNull('objection_tags');
            })
            ->exists();

        if ($objections === [] && ! $hasRecentObjectionInteraction) {
            $missing[] = 'Типичные возражения';
        }

        $hasRecentInteraction = $contractor->interactions()
            ->where('contacted_at', '>=', now()->subDays(90))
            ->exists();

        if (! $hasRecentInteraction) {
            $missing[] = 'Контакт за последние 90 дней';
        }

        return $missing;
    }

    public function calculatePercent(Contractor $contractor): int
    {
        $weights = [
            'contact_role' => 20,
            'communication_style' => 15,
            'preferred_channel' => 10,
            'success_criteria' => 20,
            'objections' => 15,
            'recent_interaction' => 20,
        ];

        $score = 0;
        $portrait = $contractor->portrait;
        $contacts = $contractor->relationLoaded('contacts')
            ? $contractor->contacts
            : $contractor->contacts()->get();

        $hasRoleContact = $contacts->contains(
            fn ($contact): bool => filled($contact->role_in_deal)
                && $contact->role_in_deal !== ContractorPortraitDictionary::UNKNOWN
        ) || $contacts->contains(fn ($contact): bool => (bool) $contact->is_decision_maker);

        if ($hasRoleContact) {
            $score += $weights['contact_role'];
        }

        if (($portrait?->communication_style ?? ContractorPortraitDictionary::UNKNOWN) !== ContractorPortraitDictionary::UNKNOWN) {
            $score += $weights['communication_style'];
        }

        if (($portrait?->preferred_channel ?? ContractorPortraitDictionary::UNKNOWN) !== ContractorPortraitDictionary::UNKNOWN) {
            $score += $weights['preferred_channel'];
        }

        if (filled($portrait?->success_criteria)) {
            $score += $weights['success_criteria'];
        }

        $objections = is_array($portrait?->typical_objections) ? $portrait->typical_objections : [];
        $hasRecentObjectionInteraction = $contractor->interactions()
            ->where('contacted_at', '>=', now()->subDays(180))
            ->where(function ($query): void {
                $query->where('outcome_code', 'objection')
                    ->orWhereNotNull('objection_tags');
            })
            ->exists();

        if ($objections !== [] || $hasRecentObjectionInteraction) {
            $score += $weights['objections'];
        }

        if ($contractor->interactions()->where('contacted_at', '>=', now()->subDays(90))->exists()) {
            $score += $weights['recent_interaction'];
        }

        return min(100, max(0, $score));
    }
}
