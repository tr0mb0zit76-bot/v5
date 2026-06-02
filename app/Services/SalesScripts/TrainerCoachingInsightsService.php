<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesTrainerDialogQuality;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTrainerMessage;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TrainerCoachingInsightsService
{
    public function __construct(
        private readonly TrainerDialogLoopDetector $loopDetector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function insights(User $user, int $days = 30, ?int $filterUserId = null, int $sampleLimit = 15): array
    {
        if (! RoleAccess::canViewTrainerAnalytics($user) && ! RoleAccess::canViewAiAnalytics($user)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к аналитике тренажёра.',
            ];
        }

        $days = max(1, min(365, $days));
        $sampleLimit = max(5, min(50, $sampleLimit));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);
        $canViewAll = $this->canViewAllTrainerSessions($user);

        $baseQuery = $this->scopedSessionsQuery($user, $since, $canViewAll, $filterUserId);

        $totalSessions = (clone $baseQuery)->count();
        $stuckSessions = (clone $baseQuery)
            ->where('trainer_dialog_quality', SalesTrainerDialogQuality::Stuck->value)
            ->count();
        $failureSessions = (clone $baseQuery)
            ->where('trainer_dialog_quality', SalesTrainerDialogQuality::Failure->value)
            ->count();

        $sessionIds = (clone $baseQuery)->pluck('id');

        $negativeMessages = $sessionIds->isEmpty()
            ? 0
            : SalesScriptTrainerMessage::query()
                ->whereIn('sales_script_play_session_id', $sessionIds)
                ->where(function (Builder $builder): void {
                    $builder->where('auto_peer_reaction', 'negative')
                        ->orWhere('peer_reaction', 'negative');
                })
                ->count();

        $loopStats = $this->analyzeLoops($baseQuery, $sampleLimit);

        $hotspotsByProfile = $this->hotspotsByProfile($baseQuery);
        $hotspotsByScript = $this->hotspotsByScript($baseQuery);

        $recommendations = $this->buildRecommendations(
            $totalSessions,
            $stuckSessions,
            $failureSessions,
            $negativeMessages,
            $loopStats,
            $hotspotsByProfile,
        );

        return [
            'available' => true,
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'scope' => $canViewAll ? 'all' : 'self',
            'summary' => [
                'total_sessions' => $totalSessions,
                'stuck_sessions' => $stuckSessions,
                'failure_sessions' => $failureSessions,
                'stuck_rate_pct' => $totalSessions > 0 ? round($stuckSessions / $totalSessions * 100, 1) : 0.0,
                'loop_detected_sessions' => $loopStats['loop_detected_sessions'],
                'negative_reaction_messages' => $negativeMessages,
            ],
            'loop_reason_counts' => $loopStats['reason_counts'],
            'hotspots_by_profile' => $hotspotsByProfile,
            'hotspots_by_script' => $hotspotsByScript,
            'sample_problem_sessions' => $loopStats['sample_sessions'],
            'recommendations' => $recommendations,
        ];
    }

    private function canViewAllTrainerSessions(User $user): bool
    {
        if ($user->isAdmin() || $user->hasRole('supervisor')) {
            return true;
        }

        return RoleAccess::canViewAiAnalytics($user);
    }

    /**
     * @return Builder<SalesScriptPlaySession>
     */
    private function scopedSessionsQuery(
        User $user,
        CarbonImmutable $since,
        bool $canViewAll,
        ?int $filterUserId,
    ): Builder {
        $query = SalesScriptPlaySession::query()
            ->where('is_trainer', true)
            ->where('created_at', '>=', $since);

        if ($canViewAll) {
            if ($filterUserId !== null && $filterUserId > 0) {
                $query->where('user_id', $filterUserId);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $baseQuery
     * @return array{
     *     loop_detected_sessions: int,
     *     reason_counts: array<string, int>,
     *     sample_sessions: list<array<string, mixed>>
     * }
     */
    private function analyzeLoops(Builder $baseQuery, int $sampleLimit): array
    {
        /** @var Collection<int, SalesScriptPlaySession> $sessions */
        $sessions = (clone $baseQuery)
            ->with(['trainerMessages', 'version.script'])
            ->where(function (Builder $builder): void {
                $builder->where('trainer_dialog_quality', SalesTrainerDialogQuality::Stuck->value)
                    ->orWhere('trainer_dialog_quality', SalesTrainerDialogQuality::Failure->value)
                    ->orWhereNotNull('completed_at');
            })
            ->orderByDesc('created_at')
            ->limit(min(40, $sampleLimit * 2))
            ->get();

        $reasonCounts = [];
        $loopDetectedSessions = 0;
        $samples = [];

        foreach ($sessions as $session) {
            if ($session->trainerMessages->count() < 4) {
                continue;
            }

            $loop = $this->loopDetector->analyze($session->trainerMessages);

            if (! $loop['loop_detected']) {
                continue;
            }

            $loopDetectedSessions++;

            foreach ($loop['reasons'] as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }

            if (count($samples) >= $sampleLimit) {
                continue;
            }

            $scriptTitle = $session->version?->script?->title;

            $samples[] = [
                'session_id' => $session->id,
                'created_at' => $session->created_at?->toIso8601String(),
                'trainer_profile_title' => $session->trainer_profile_title,
                'trainer_dialog_quality' => $session->trainer_dialog_quality?->value,
                'script_label' => $scriptTitle
                    ? "{$scriptTitle} · v{$session->version?->version_number}"
                    : null,
                'loop_severity' => $loop['severity'],
                'loop_reasons' => $loop['reasons'],
                'message_count' => $session->trainerMessages->count(),
            ];
        }

        arsort($reasonCounts);

        return [
            'loop_detected_sessions' => $loopDetectedSessions,
            'reason_counts' => $reasonCounts,
            'sample_sessions' => $samples,
        ];
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $baseQuery
     * @return list<array<string, mixed>>
     */
    private function hotspotsByProfile(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->toBase()
            ->selectRaw('trainer_profile_key as profile_key')
            ->selectRaw('MAX(trainer_profile_title) as profile_title')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN trainer_dialog_quality = ? THEN 1 ELSE 0 END) as stuck',
                [SalesTrainerDialogQuality::Stuck->value],
            )
            ->groupBy('trainer_profile_key')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('stuck')
            ->limit(10)
            ->get();

        return $rows->map(function (object $row): array {
            $total = (int) $row->total;
            $stuck = (int) $row->stuck;

            return [
                'profile_key' => $row->profile_key ? (string) $row->profile_key : null,
                'profile_title' => (string) ($row->profile_title ?: '—'),
                'total' => $total,
                'stuck' => $stuck,
                'stuck_rate_pct' => $total > 0 ? round($stuck / $total * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $baseQuery
     * @return list<array<string, mixed>>
     */
    private function hotspotsByScript(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->toBase()
            ->selectRaw('sales_script_version_id as version_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN trainer_dialog_quality = ? THEN 1 ELSE 0 END) as stuck',
                [SalesTrainerDialogQuality::Stuck->value],
            )
            ->groupBy('sales_script_version_id')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('stuck')
            ->limit(10)
            ->get();

        $versionIds = $rows->pluck('version_id')->filter()->unique();
        $labels = SalesScriptPlaySession::query()
            ->whereIn('sales_script_version_id', $versionIds)
            ->with('version.script')
            ->get()
            ->unique('sales_script_version_id')
            ->mapWithKeys(function (SalesScriptPlaySession $session): array {
                $title = $session->version?->script?->title;
                $label = $title
                    ? "{$title} · v{$session->version?->version_number}"
                    : "v{$session->version?->version_number}";

                return [(int) $session->sales_script_version_id => $label];
            });

        return $rows->map(function (object $row) use ($labels): array {
            $total = (int) $row->total;
            $stuck = (int) $row->stuck;
            $versionId = (int) $row->version_id;

            return [
                'version_id' => $versionId,
                'script_label' => (string) ($labels[$versionId] ?? "v{$versionId}"),
                'total' => $total,
                'stuck' => $stuck,
                'stuck_rate_pct' => $total > 0 ? round($stuck / $total * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $hotspotsByProfile
     * @param  array<string, mixed>  $loopStats
     * @return list<string>
     */
    private function buildRecommendations(
        int $totalSessions,
        int $stuckSessions,
        int $failureSessions,
        int $negativeMessages,
        array $loopStats,
        array $hotspotsByProfile,
    ): array {
        $recommendations = [];

        if ($totalSessions === 0) {
            return ['За период нет сессий тренажёра — соберите больше тренировок для анализа.'];
        }

        $stuckRate = $stuckSessions / max(1, $totalSessions);

        if ($stuckRate >= 0.2) {
            $recommendations[] = 'Высокая доля тупиков — пересмотрите подсказки в узлах сценария и добавьте ветки для частых возражений.';
        }

        if (($loopStats['reason_counts']['assistant_repeated_reply'] ?? 0) >= 2) {
            $recommendations[] = 'AI часто повторяет реплики — усильте trainer_assistant_instructions: запрет на дословные повторы, смена угла, конкретный следующий шаг.';
        }

        if (($loopStats['reason_counts']['user_repeated_question'] ?? 0) >= 2) {
            $recommendations[] = 'Менеджеры повторяют вопросы — добавьте в Книгу продаж блок «как отвечать на …» и короткий коучинг в тренажёре.';
        }

        if ($negativeMessages >= 5) {
            $recommendations[] = 'Много негативных auto-оценок реплик AI — проверьте промпт тренажёра и профили клиентов с худшими показателями.';
        }

        if ($hotspotsByProfile !== [] && ($hotspotsByProfile[0]['stuck_rate_pct'] ?? 0) >= 30) {
            $recommendations[] = sprintf(
                'Профиль «%s» чаще уходит в тупик — дополните сценарий подсказками или статью в Книге продаж.',
                (string) $hotspotsByProfile[0]['profile_title'],
            );
        }

        if ($failureSessions > $stuckSessions && $failureSessions >= 3) {
            $recommendations[] = 'Много неудачных диалогов — разберите sample_problem_sessions и обновите скрипт или обучающие материалы.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Критичных паттернов зацикливания не видно; продолжайте мониторинг и оценки реплик в тренажёре.';
        }

        return $recommendations;
    }
}
