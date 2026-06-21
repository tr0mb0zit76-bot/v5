<?php

namespace App\Services\Contractor;

use App\Models\Contractor;
use App\Models\ContractorInteraction;
use App\Models\ContractorPortrait;
use App\Models\User;
use App\Support\ContractorPortraitDictionary;
use Illuminate\Support\Carbon;

class ContractorPortraitService
{
    public function __construct(
        private readonly ContractorPortraitCoverage $coverage,
    ) {}

    public function getOrCreate(Contractor $contractor): ContractorPortrait
    {
        $contractor->loadMissing('portrait');

        if ($contractor->portrait !== null) {
            return $contractor->portrait;
        }

        $portrait = $contractor->portrait()->create([
            'communication_style' => ContractorPortraitDictionary::UNKNOWN,
            'price_sensitivity' => ContractorPortraitDictionary::UNKNOWN,
            'preferred_channel' => ContractorPortraitDictionary::UNKNOWN,
            'decision_cadence' => ContractorPortraitDictionary::UNKNOWN,
            'relationship_trust' => ContractorPortraitDictionary::UNKNOWN,
            'coverage_pct' => 0,
        ]);

        $contractor->setRelation('portrait', $portrait);

        return $portrait;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePortrait(Contractor $contractor, array $data, User $user): ContractorPortrait
    {
        $portrait = $this->getOrCreate($contractor);

        $objections = $data['typical_objections'] ?? null;
        if (is_array($objections)) {
            $objections = array_values(array_filter(array_map(
                fn ($tag): string => trim((string) $tag),
                $objections,
            ), fn (string $tag): bool => $tag !== ''));
        }

        $portrait->fill([
            'communication_style' => $data['communication_style'] ?? $portrait->communication_style,
            'price_sensitivity' => $data['price_sensitivity'] ?? $portrait->price_sensitivity,
            'preferred_channel' => $data['preferred_channel'] ?? $portrait->preferred_channel,
            'decision_cadence' => $data['decision_cadence'] ?? $portrait->decision_cadence,
            'relationship_trust' => $data['relationship_trust'] ?? $portrait->relationship_trust,
            'success_criteria' => array_key_exists('success_criteria', $data)
                ? $this->nullableString($data['success_criteria'])
                : $portrait->success_criteria,
            'typical_objections' => array_key_exists('typical_objections', $data)
                ? ($objections ?: null)
                : $portrait->typical_objections,
            'internal_notes' => array_key_exists('internal_notes', $data)
                ? $this->nullableString($data['internal_notes'])
                : $portrait->internal_notes,
            'portrait_updated_at' => now(),
            'updated_by' => $user->id,
        ]);

        $portrait->coverage_pct = $this->coverage->calculatePercent(
            $contractor->setRelation('portrait', $portrait),
        );
        $portrait->save();

        return $portrait->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeInteraction(Contractor $contractor, array $data, User $user): ContractorInteraction
    {
        $interaction = $contractor->interactions()->create([
            'contractor_contact_id' => $data['contractor_contact_id'] ?? null,
            'contacted_at' => Carbon::parse((string) $data['contacted_at']),
            'channel' => (string) $data['channel'],
            'outcome_code' => $data['outcome_code'] ?? null,
            'next_contact_at' => filled($data['next_contact_at'] ?? null)
                ? Carbon::parse((string) $data['next_contact_at'])
                : null,
            'subject' => $this->nullableString($data['subject'] ?? null),
            'summary' => (string) ($data['summary'] ?? ''),
            'result' => $this->nullableString($data['result'] ?? null),
            'objection_tags' => $this->normalizeTags($data['objection_tags'] ?? []),
            'merge_to_portrait' => (bool) ($data['merge_to_portrait'] ?? false),
            'created_by' => $user->id,
        ]);

        if ($interaction->merge_to_portrait) {
            $this->mergeFromInteraction($contractor, $interaction, $user);
        } else {
            $this->recalculateCoverage($contractor);
        }

        return $interaction->fresh(['author:id,name', 'contact:id,full_name,role_in_deal']);
    }

    /**
     * @param  array<string, mixed>  $qualification
     * @return array{proposed: array<string, mixed>, skipped: list<string>}
     */
    public function previewMergeFromLead(Contractor $contractor, array $qualification): array
    {
        $portrait = $this->getOrCreate($contractor);
        $contractor->setRelation('portrait', $portrait);

        return $this->buildLeadMergeProposal($portrait, $qualification);
    }

    /**
     * @param  array<string, mixed>  $qualification
     */
    public function mergeFromLead(Contractor $contractor, array $qualification, User $user): ContractorPortrait
    {
        $proposal = $this->previewMergeFromLead($contractor, $qualification);

        if ($proposal['proposed'] === []) {
            return $this->recalculateCoverage($contractor);
        }

        return $this->updatePortrait($contractor, $proposal['proposed'], $user);
    }

    public function mergeFromInteraction(Contractor $contractor, ContractorInteraction $interaction, User $user): ContractorPortrait
    {
        $updates = [];

        if ($interaction->outcome_code === 'objection') {
            $tags = is_array($interaction->objection_tags) ? $interaction->objection_tags : [];
            if ($tags !== []) {
                $portrait = $this->getOrCreate($contractor);
                $existing = is_array($portrait->typical_objections) ? $portrait->typical_objections : [];
                $updates['typical_objections'] = array_values(array_unique([...$existing, ...$tags]));
            }
        }

        if ($interaction->channel !== '') {
            $updates['preferred_channel'] = $interaction->channel;
        }

        if ($updates === []) {
            return $this->recalculateCoverage($contractor);
        }

        return $this->updatePortrait($contractor, $updates, $user);
    }

    public function recalculateCoverage(Contractor $contractor): ContractorPortrait
    {
        $portrait = $this->getOrCreate($contractor);
        $portrait->update([
            'coverage_pct' => $this->coverage->calculatePercent($contractor->fresh(['portrait', 'contacts'])),
        ]);

        return $portrait->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePortrait(?ContractorPortrait $portrait, Contractor $contractor): array
    {
        $portrait ??= $this->getOrCreate($contractor);
        $contractor->setRelation('portrait', $portrait);

        return [
            'communication_style' => $portrait->communication_style,
            'price_sensitivity' => $portrait->price_sensitivity,
            'preferred_channel' => $portrait->preferred_channel,
            'decision_cadence' => $portrait->decision_cadence,
            'relationship_trust' => $portrait->relationship_trust,
            'success_criteria' => $portrait->success_criteria,
            'typical_objections' => is_array($portrait->typical_objections) ? $portrait->typical_objections : [],
            'internal_notes' => $portrait->internal_notes,
            'coverage_pct' => (int) $portrait->coverage_pct,
            'missing_slots' => $this->coverage->missingSlots($contractor),
            'portrait_updated_at' => optional($portrait->portrait_updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  list<mixed>|null  $tags
     * @return list<string>|null
     */
    private function normalizeTags(?array $tags): ?array
    {
        if ($tags === null || $tags === []) {
            return null;
        }

        $normalized = array_values(array_filter(array_map(
            fn ($tag): string => trim((string) $tag),
            $tags,
        ), fn (string $tag): bool => $tag !== ''));

        return $normalized === [] ? null : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $qualification
     * @return array{proposed: array<string, mixed>, skipped: list<string>}
     */
    private function buildLeadMergeProposal(ContractorPortrait $portrait, array $qualification): array
    {
        $proposed = [];
        $skipped = [];

        $need = $this->nullableString($qualification['need'] ?? null);
        if ($need !== null) {
            if (blank($portrait->success_criteria)) {
                $proposed['success_criteria'] = $need;
            } else {
                $skipped[] = 'Потребность — критерии успеха уже заполнены';
            }
        }

        $authority = $this->nullableString($qualification['authority'] ?? null);
        if ($authority !== null) {
            $noteLine = 'ЛПР (из квалификации лида): '.$authority;
            if (blank($portrait->internal_notes)) {
                $proposed['internal_notes'] = $noteLine;
            } elseif (! str_contains((string) $portrait->internal_notes, $authority)) {
                $proposed['internal_notes'] = trim((string) $portrait->internal_notes)."\n".$noteLine;
            } else {
                $skipped[] = 'ЛПР — уже есть во внутренней памятке';
            }
        }

        $budget = $this->nullableString($qualification['budget'] ?? null);
        if ($budget !== null) {
            $inferredSensitivity = $this->inferPriceSensitivityFromBudget($budget);
            if (($portrait->price_sensitivity ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN
                && $inferredSensitivity !== ContractorPortraitDictionary::UNKNOWN) {
                $proposed['price_sensitivity'] = $inferredSensitivity;
            } elseif (($portrait->price_sensitivity ?? ContractorPortraitDictionary::UNKNOWN) !== ContractorPortraitDictionary::UNKNOWN) {
                $skipped[] = 'Бюджет — чувствительность к цене уже указана';
            }

            $budgetLine = 'Бюджет (из квалификации лида): '.$budget;
            $currentNotes = (string) ($proposed['internal_notes'] ?? $portrait->internal_notes ?? '');
            if ($currentNotes === '' || ! str_contains($currentNotes, $budget)) {
                $proposed['internal_notes'] = $currentNotes === ''
                    ? $budgetLine
                    : trim($currentNotes)."\n".$budgetLine;
            }
        }

        $timeline = $this->nullableString($qualification['timeline'] ?? null);
        if ($timeline !== null) {
            $inferredCadence = $this->inferDecisionCadenceFromTimeline($timeline);
            if (($portrait->decision_cadence ?? ContractorPortraitDictionary::UNKNOWN) === ContractorPortraitDictionary::UNKNOWN
                && $inferredCadence !== ContractorPortraitDictionary::UNKNOWN) {
                $proposed['decision_cadence'] = $inferredCadence;
            } elseif (($portrait->decision_cadence ?? ContractorPortraitDictionary::UNKNOWN) !== ContractorPortraitDictionary::UNKNOWN) {
                $skipped[] = 'Срок — скорость решений уже указана';
            }

            $timelineLine = 'Срок (из квалификации лида): '.$timeline;
            $currentNotes = (string) ($proposed['internal_notes'] ?? $portrait->internal_notes ?? '');
            if ($currentNotes === '' || ! str_contains($currentNotes, $timeline)) {
                $proposed['internal_notes'] = $currentNotes === ''
                    ? $timelineLine
                    : trim($currentNotes)."\n".$timelineLine;
            }
        }

        if ($proposed === []) {
            return ['proposed' => [], 'skipped' => $skipped !== [] ? $skipped : ['Нет новых данных для переноса']];
        }

        return ['proposed' => $proposed, 'skipped' => $skipped];
    }

    private function inferPriceSensitivityFromBudget(string $budget): string
    {
        $normalized = mb_strtolower($budget);

        foreach (['эконом', 'дешев', 'огранич', 'скид', 'жмут', 'миним', 'бюджет'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'high';
            }
        }

        foreach (['не принцип', 'гибк', 'качеств', 'скорее сервис'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'low';
            }
        }

        return ContractorPortraitDictionary::UNKNOWN;
    }

    private function inferDecisionCadenceFromTimeline(string $timeline): string
    {
        $normalized = mb_strtolower($timeline);

        foreach (['сроч', 'сегодня', 'завтра', 'asap', 'немедлен', 'сразу'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'fast';
            }
        }

        foreach (['комитет', 'согласован', 'директор', 'несколько', 'юрист'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'committee';
            }
        }

        foreach (['долг', 'месяц', 'квартал', 'не спеш'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return 'slow';
            }
        }

        return ContractorPortraitDictionary::UNKNOWN;
    }
}
