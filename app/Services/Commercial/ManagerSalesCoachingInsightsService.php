<?php

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\User;
use App\Support\LeadCloseOutcomeFlagCatalog;
use App\Support\LeadViewAuthorization;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ManagerSalesCoachingInsightsService
{
    public function __construct(
        private readonly ManagerDealSignalExtractor $signalExtractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function insights(User $user, int $days = 90, ?int $filterUserId = null, int $sampleLimit = 10): array
    {
        if (! RoleAccess::canViewSalesCoachingInsights($user)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к коучингу по воронке.',
            ];
        }

        $days = max(1, min(365, $days));
        $sampleLimit = max(3, min(25, $sampleLimit));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);
        $canViewAll = $this->canViewAllManagers($user);

        $baseQuery = $this->scopedClosedLeadsQuery($user, $since, $canViewAll, $filterUserId);

        $closedLeads = (clone $baseQuery)->get();
        $won = $closedLeads->where('status', 'won');
        $lost = $closedLeads->where('status', 'lost');
        $totalClosed = $closedLeads->count();

        $signals = $closedLeads->map(fn (Lead $lead): array => $this->signalExtractor->extract($lead));

        $wonSignals = $signals->filter(fn (array $s): bool => $s['outcome'] === 'won')->values();
        $lostSignals = $signals->filter(fn (array $s): bool => $s['outcome'] === 'lost')->values();

        $lossFlagCounts = $this->countPrimaryFlags($lost);
        $hygieneGapCounts = $this->countHygieneGaps($lostSignals);
        $wonHygieneGapCounts = $this->countHygieneGaps($wonSignals);

        $idleQualLost = $lostSignals->filter(fn (array $s): bool => (bool) ($s['has_idle_qualification_dwell'] ?? false))->count();
        $idleQualWon = $wonSignals->filter(fn (array $s): bool => (bool) ($s['has_idle_qualification_dwell'] ?? false))->count();

        $recommendations = $this->buildRecommendations(
            $totalClosed,
            $won->count(),
            $lost->count(),
            $hygieneGapCounts,
            $wonHygieneGapCounts,
            $lossFlagCounts,
            $idleQualLost,
            $lost->count(),
        );

        return [
            'available' => true,
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'scope' => $canViewAll ? 'all' : 'self',
            'summary' => [
                'closed_leads' => $totalClosed,
                'won_leads' => $won->count(),
                'lost_leads' => $lost->count(),
                'win_rate_pct' => $totalClosed > 0 ? round($won->count() / $totalClosed * 100, 1) : 0.0,
                'lost_without_authority' => $hygieneGapCounts['no_authority'] ?? 0,
                'lost_with_idle_qualification' => $idleQualLost,
                'won_with_idle_qualification' => $idleQualWon,
            ],
            'loss_flag_counts' => $lossFlagCounts,
            'lost_hygiene_gap_counts' => $hygieneGapCounts,
            'won_hygiene_gap_counts' => $wonHygieneGapCounts,
            'contrast_samples' => $this->contrastSamples($wonSignals, $lostSignals, $sampleLimit),
            'recommendations' => $recommendations,
        ];
    }

    private function canViewAllManagers(User $user): bool
    {
        if ($user->isAdmin() || $user->hasRole('supervisor')) {
            return true;
        }

        return RoleAccess::canViewAiAnalytics($user);
    }

    /**
     * @return Builder<Lead>
     */
    private function scopedClosedLeadsQuery(
        User $user,
        CarbonImmutable $since,
        bool $canViewAll,
        ?int $filterUserId,
    ): Builder {
        $query = Lead::query()
            ->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', $since);

        if ($canViewAll) {
            if ($filterUserId !== null && $filterUserId > 0) {
                $query->where('responsible_id', $filterUserId);
            }
        } else {
            LeadViewAuthorization::applyLeadsVisibilityScope($query, $user);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Lead>  $lost
     * @return array<string, int>
     */
    private function countPrimaryFlags($lost): array
    {
        $counts = [];

        foreach ($lost as $lead) {
            $flag = $lead->close_outcome_primary_flag;

            if ($flag === null || $flag === '') {
                $counts['unset'] = ($counts['unset'] ?? 0) + 1;

                continue;
            }

            $counts[$flag] = ($counts[$flag] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<string, int>
     */
    private function countHygieneGaps($signals): array
    {
        $counts = [];

        foreach ($signals as $signal) {
            foreach ($signal['hygiene_gaps'] ?? [] as $gap) {
                $counts[$gap] = ($counts[$gap] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $wonSignals
     * @param  Collection<int, array<string, mixed>>  $lostSignals
     * @return list<array<string, mixed>>
     */
    private function contrastSamples($wonSignals, $lostSignals, int $limit): array
    {
        $samples = [];

        foreach ($lostSignals->take($limit) as $lost) {
            $similarWon = $wonSignals->first(fn (array $won): bool => $this->similarHygieneProfile($won, $lost));

            $samples[] = [
                'lost' => [
                    'lead_id' => $lost['lead_id'],
                    'lead_number' => $lost['lead_number'],
                    'title' => $lost['title'],
                    'hygiene_score' => $lost['hygiene_score'],
                    'hygiene_gaps' => $lost['hygiene_gaps'],
                    'close_outcome_label' => $lost['close_outcome_primary_label'],
                    'has_idle_qualification_dwell' => $lost['has_idle_qualification_dwell'],
                ],
                'won_reference' => $similarWon !== null ? [
                    'lead_id' => $similarWon['lead_id'],
                    'lead_number' => $similarWon['lead_number'],
                    'title' => $similarWon['title'],
                    'hygiene_score' => $similarWon['hygiene_score'],
                ] : null,
            ];
        }

        return $samples;
    }

    /**
     * @param  array<string, mixed>  $won
     * @param  array<string, mixed>  $lost
     */
    private function similarHygieneProfile(array $won, array $lost): bool
    {
        return ($won['hygiene_score'] ?? 0) >= 60 && ($lost['hygiene_score'] ?? 0) < 50;
    }

    /**
     * @param  array<string, int>  $lossFlagCounts
     * @param  array<string, int>  $hygieneGapCounts
     * @param  array<string, int>  $wonHygieneGapCounts
     * @return list<string>
     */
    private function buildRecommendations(
        int $totalClosed,
        int $wonCount,
        int $lostCount,
        array $hygieneGapCounts,
        array $wonHygieneGapCounts,
        array $lossFlagCounts,
        int $idleQualLost,
        int $lostTotal,
    ): array {
        if ($totalClosed === 0) {
            return ['За период нет закрытых лидов — закройте несколько сделок с указанием причины для анализа.'];
        }

        $recommendations = [];
        $lostWithoutAuthority = $hygieneGapCounts['no_authority'] ?? 0;
        $wonWithoutAuthority = $wonHygieneGapCounts['no_authority'] ?? 0;

        if ($lostTotal > 0 && $lostWithoutAuthority >= max(2, (int) ceil($lostTotal * 0.4))) {
            $recommendations[] = sprintf(
                'В %d из %d проигранных лидов не указан ЛПР — у выигранных это реже (%d). На этапе квалификации фиксируйте контакт и полномочия.',
                $lostWithoutAuthority,
                $lostTotal,
                $wonWithoutAuthority,
            );
        }

        if ($idleQualLost >= 2 && $lostTotal > 0 && $idleQualLost >= ($lostTotal * 0.3)) {
            $recommendations[] = 'На этапе квалификации часто долгое «молчание» без задач и событий в ленте — это не подготовка, а простой. Ставьте next step и фиксируйте контакт в лиде.';
        }

        if (($hygieneGapCounts['no_proposal_sent'] ?? 0) >= 3) {
            $recommendations[] = 'Много проигрышей без отправленного КП — проверьте, доходите ли до расчёта и предложения.';
        }

        $topFlag = array_key_first($lossFlagCounts);

        if ($topFlag !== null && $topFlag !== 'unset' && ($lossFlagCounts[$topFlag] ?? 0) >= 2) {
            $label = LeadCloseOutcomeFlagCatalog::label($topFlag) ?? $topFlag;
            $recommendations[] = "Частая причина отказа: «{$label}» — разберите 2–3 таких лида и обновите скрипт или Книгу продаж.";
        }

        if (($lossFlagCounts['unset'] ?? 0) >= max(2, (int) ceil($lostTotal * 0.3))) {
            $recommendations[] = 'У значительной доли проигрышей не указана причина закрытия — отмечайте флаг при переводе на финальный этап.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Явных системных пробелов не видно; продолжайте отмечать причины закрытия и квалификацию в карточке лида.';
        }

        return $recommendations;
    }
}
