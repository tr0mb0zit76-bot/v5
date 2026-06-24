<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\ContractorContact;
use Illuminate\Support\Collection;

/**
 * Человекочитаемая подпись ЛПР для квалификации лида.
 */
final class ContractorDecisionMakerLabel
{
    public static function resolve(Contractor $contractor): ?string
    {
        $contractor->loadMissing('contacts');

        /** @var Collection<int, ContractorContact> $contacts */
        $contacts = $contractor->contacts;

        $decisionMaker = $contacts->first(
            fn (ContractorContact $contact): bool => (bool) $contact->is_decision_maker,
        ) ?? $contacts->first(
            fn (ContractorContact $contact): bool => ($contact->role_in_deal ?? '') === 'decision_maker',
        ) ?? $contacts->first(
            fn (ContractorContact $contact): bool => (bool) $contact->is_primary,
        );

        if ($decisionMaker !== null && filled($decisionMaker->full_name)) {
            return self::formatNameWithPosition($decisionMaker->full_name, $decisionMaker->position);
        }

        if (filled($contractor->contact_person)) {
            return self::formatNameWithPosition(
                (string) $contractor->contact_person,
                $contractor->contact_person_position,
            );
        }

        if (filled($contractor->signer_name_nominative)) {
            return self::formatNameWithPosition(
                (string) $contractor->signer_name_nominative,
                $contractor->signer_position,
            );
        }

        return null;
    }

    private static function formatNameWithPosition(string $name, ?string $position): string
    {
        $name = trim($name);
        $position = trim((string) $position);

        if ($position === '') {
            return $name;
        }

        return $name.', '.$position;
    }
}
