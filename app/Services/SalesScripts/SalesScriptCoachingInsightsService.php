<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlaySessionOutcome;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\User;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SalesScriptCoachingInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function insights(User $user, int $days = 30, ?int $filterUserId = null, int $sampleLimit = 15): array
    {
        if (! RoleAccess::canViewTrainerAnalytics($user) && ! RoleAccess::canViewAiAnalytics($user)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к аналитике скриптов.',
            ];
        }

        $days = max(1, min(365, $days));
        $sampleLimit = max(5, min(50, $sampleLimit));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);
        $canViewAll = $this->canViewAllSessions($user);

        $baseQuery = $this->scopedSessionsQuery($user, $since, $canViewAll, $filterUserId);

        $totalSessions = (clone $baseQuery)->count();
        $completedSessions = (clone $baseQuery)->whereNotNull('completed_at')->count();
        $abandonedSessions = max(0, $totalSessions - $completedSessions);

        $outcomeBreakdown = $this->outcomeBreakdown($baseQuery);
        $topObjections = $this->topObjections($baseQuery);
        $weakPerformers = $this->weakPerformers($baseQuery, $canViewAll);
        $hotspotsByScript = $this->hotspotsByScript($baseQuery);
        $sampleSessions = $this->sampleProblemSessions($baseQuery, $sampleLimit);

        $recommendations = $this->buildRecommendations(
            $totalSessions,
            $completedSessions,
            $abandonedSessions,
            $outcomeBreakdown,
            $topObjections,
            $weakPerformers,
            $hotspotsByScript,
        );

        return [
            'available' => true,
            'mode' => 'live_scripts',
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'scope' => $canViewAll ? 'all' : 'self',
            'summary' => [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'abandoned_sessions' => $abandonedSessions,
                'completion_rate_pct' => $totalSessions > 0 ? round($completedSessions / $totalSessions * 100, 1) : 0.0,
            ],
            'outcome_breakdown' => $outcomeBreakdown,
            'top_objections' => $topObjections,
            'weak_performers' => $weakPerformers,
            'hotspots_by_script' => $hotspotsByScript,
            'sample_problem_sessions' => $sampleSessions,
            'recommendations' => $recommendations,
        ];
    }

    private function canViewAllSessions(User $user): bool
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
            ->where('is_trainer', false)
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
     * @return list<array{outcome: string, label: string, count: int, share_pct: float}>
     */
    private function outcomeBreakdown(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull('completed_at')
            ->whereNotNull('outcome')
            ->toBase()
            ->selectRaw('outcome')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('outcome')
            ->orderByDesc('total')
            ->get();

        $grandTotal = (int) $rows->sum('total');

        return $rows->map(function (object $row) use ($grandTotal): array {
            $outcome = (string) $row->outcome;
            $count = (int) $row->total;

            return [
                'outcome' => $outcome,
                'label' => $this->outcomeLabel($outcome),
                'count' => $count,
                'share_pct' => $grandTotal > 0 ? round($count / $grandTotal * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $baseQuery
     * @return list<array{reaction_class_id: int, key: string|null, label: string, count: int}>
     */
    private function topObjections(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull('primary_reaction_class_id')
            ->toBase()
            ->selectRaw('primary_reaction_class_id as reaction_class_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('primary_reaction_class_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $classIds = $rows->pluck('reaction_class_id')->filter()->unique();
        /** @var Collection<int, SalesScriptReactionClass> $classes */
        $classes = SalesScriptReactionClass::query()
            ->whereIn('id', $classIds)
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($classes): array {
            $id = (int) $row->reaction_class_id;
            $class = $classes->get($id);

            return [
                'reaction_class_id' => $id,
                'key' => $class?->key,
                'label' => (string) ($class?->label ?? "reaction#{$id}"),
                'count' => (int) $row->total,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $baseQuery
     * @return list<array{user_id: int, user_name: string, total: int, lost: int, lost_rate_pct: float}>
     */
    private function weakPerformers(Builder $baseQuery, bool $canViewAll): array
    {
        if (! $canViewAll) {
            return [];
        }

        $rows = (clone $baseQuery)
            ->whereNotNull('completed_at')
            ->toBase()
            ->selectRaw('user_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END) as lost',
                [SalesPlaySessionOutcome::Lost->value],
            )
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('lost')
            ->limit(10)
            ->get();

        $userIds = $rows->pluck('user_id')->filter()->unique();
        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return $rows->map(function (object $row) use ($names): array {
            $total = (int) $row->total;
            $lost = (int) $row->lost;
            $userId = (int) $row->user_id;

            return [
                'user_id' => $userId,
                'user_name' => (string) ($names[$userId] ?? "user#{$userId}"),
                'total' => $total,
                'lost' => $lost,
                'lost_rate_pct' => $total > 0 ? round($lost / $total * 100, 1) : 0.0,
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
                'SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END) as lost',
                [SalesPlaySessionOutcome::Lost->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END) as abandoned',
            )
            ->groupBy('sales_script_version_id')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('lost')
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
            $lost = (int) $row->lost;
            $versionId = (int) $row->version_id;

            return [
                'version_id' => $versionId,
                'script_label' => (string) ($labels[$versionId] ?? "v{$versionId}"),
                'total' => $total,
                'lost' => $lost,
                'abandoned' => (int) $row->abandoned,
                'lost_rate_pct' => $total > 0 ? round($lost / $total * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $baseQuery
     * @return list<array<string, mixed>>
     */
    private function sampleProblemSessions(Builder $baseQuery, int $sampleLimit): array
    {
        /** @var Collection<int, SalesScriptPlaySession> $sessions */
        $sessions = (clone $baseQuery)
            ->with(['version.script', 'user', 'primaryReactionClass'])
            ->where(function (Builder $builder): void {
                $builder->whereNull('completed_at')
                    ->orWhere('outcome', SalesPlaySessionOutcome::Lost->value)
                    ->orWhere('outcome', SalesPlaySessionOutcome::NoContact->value);
            })
            ->orderByDesc('created_at')
            ->limit($sampleLimit)
            ->get();

        return $sessions->map(function (SalesScriptPlaySession $session): array {
            $scriptTitle = $session->version?->script?->title;

            return [
                'session_id' => $session->id,
                'user_name' => $session->user?->name,
                'created_at' => $session->created_at?->toIso8601String(),
                'completed_at' => $session->completed_at?->toIso8601String(),
                'outcome' => $session->outcome?->value,
                'primary_objection' => $session->primaryReactionClass?->label,
                'script_label' => $scriptTitle
                    ? "{$scriptTitle} · v{$session->version?->version_number}"
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $outcomeBreakdown
     * @param  list<array<string, mixed>>  $topObjections
     * @param  list<array<string, mixed>>  $weakPerformers
     * @param  list<array<string, mixed>>  $hotspotsByScript
     * @return list<string>
     */
    private function buildRecommendations(
        int $totalSessions,
        int $completedSessions,
        int $abandonedSessions,
        array $outcomeBreakdown,
        array $topObjections,
        array $weakPerformers,
        array $hotspotsByScript,
    ): array {
        $tips = [];

        if ($totalSessions < 5) {
            $tips[] = 'Мало сессий за период — соберите больше прохождений скриптов, чтобы рекомендации были надёжными.';

            return $tips;
        }

        if ($abandonedSessions > 0 && $abandonedSessions / max(1, $totalSessions) >= 0.25) {
            $tips[] = 'Четверть и более сессий не доводят до исхода — упростите финальный шаг и напомните менеджерам фиксировать результат.';
        }

        $lostShare = collect($outcomeBreakdown)->firstWhere('outcome', SalesPlaySessionOutcome::Lost->value)['share_pct'] ?? 0;
        if ($lostShare >= 30) {
            $tips[] = 'Высокая доля исхода «потеряно» — разберите топ возражений и обновите ветки сценария в конструкторе.';
        }

        if ($topObjections !== []) {
            $top = $topObjections[0];
            $tips[] = "Чаще всего фиксируют возражение «{$top['label']}» — добавьте отработку на соответствующих шагах и проверьте подсказки.";
        }

        if ($weakPerformers !== []) {
            $weakest = $weakPerformers[0];
            if ($weakest['lost_rate_pct'] >= 40) {
                $tips[] = "У {$weakest['user_name']} высокая доля проигрышей ({$weakest['lost_rate_pct']}%) — назначьте разбор с руководителем и тренажёр по проблемным веткам.";
            }
        }

        if ($hotspotsByScript !== []) {
            $hot = $hotspotsByScript[0];
            if ($hot['lost_rate_pct'] >= 35) {
                $tips[] = "Сценарий «{$hot['script_label']}» даёт много проигрышей — пересмотрите узлы с отвалом и A/B формулировки.";
            }
        }

        if ($tips === []) {
            $tips[] = 'Показатели в норме — продолжайте еженедельно смотреть возражения и незавершённые сессии.';
        }

        return $tips;
    }

    private function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            SalesPlaySessionOutcome::NoContact->value => 'Нет контакта',
            SalesPlaySessionOutcome::Progress->value => 'Прогресс',
            SalesPlaySessionOutcome::QuoteSent->value => 'КП отправлено',
            SalesPlaySessionOutcome::Won->value => 'Выиграно',
            SalesPlaySessionOutcome::Lost->value => 'Потеряно',
            SalesPlaySessionOutcome::Postponed->value => 'Отложено',
            default => $outcome,
        };
    }
}
