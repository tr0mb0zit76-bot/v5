<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesTrainerDialogQuality;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTrainerMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TrainerFeedbackDigestService
{
    /**
     * @return array<string, mixed>
     */
    public function digest(
        User $user,
        int $days,
        bool $canViewAll,
        ?int $filterUserId = null,
        ?string $profileKey = null,
        ?int $versionId = null,
    ): array {
        $days = max(1, min(365, $days));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);

        $trainerQuery = $this->trainerQuery($user, $since, $canViewAll, $filterUserId, $profileKey, $versionId);
        $sessionIds = (clone $trainerQuery)->pluck('id');
        $totalSessions = $sessionIds->count();

        if ($totalSessions === 0) {
            return [
                'available' => true,
                'period_days' => $days,
                'summary' => [
                    'total_sessions' => 0,
                    'negative_messages' => 0,
                    'stuck_or_failure_sessions' => 0,
                    'stuck_or_failure_rate_pct' => 0.0,
                ],
                'script_hotspots' => [],
                'node_hotspots' => [],
                'feedback_tag_hotspots' => [],
                'profile_hotspots' => [],
                'live_objections' => [],
                'missing_fields' => [],
                'recommendations' => ['Пока нет тренировочных данных для выводов. Проведите несколько сессий и отметьте качество ответов.'],
                'limitations' => $this->limitations(),
            ];
        }

        $negativeMessages = $this->negativeMessageCount($sessionIds);
        $stuckOrFailure = (clone $trainerQuery)
            ->whereIn('trainer_dialog_quality', [
                SalesTrainerDialogQuality::Stuck->value,
                SalesTrainerDialogQuality::Failure->value,
            ])
            ->count();

        $scriptHotspots = $this->scriptHotspots($trainerQuery, $sessionIds);
        $nodeHotspots = $this->nodeHotspots($sessionIds);
        $feedbackTagHotspots = $this->feedbackTagHotspots($sessionIds);
        $profileHotspots = $this->profileHotspots($trainerQuery);
        $missingFields = $this->missingFields($trainerQuery);
        $liveObjections = $this->liveObjections($user, $since, $canViewAll, $filterUserId, $versionId);

        return [
            'available' => true,
            'period_days' => $days,
            'summary' => [
                'total_sessions' => $totalSessions,
                'negative_messages' => $negativeMessages,
                'stuck_or_failure_sessions' => $stuckOrFailure,
                'stuck_or_failure_rate_pct' => round($stuckOrFailure / max(1, $totalSessions) * 100, 1),
            ],
            'script_hotspots' => $scriptHotspots,
            'node_hotspots' => $nodeHotspots,
            'feedback_tag_hotspots' => $feedbackTagHotspots,
            'profile_hotspots' => $profileHotspots,
            'live_objections' => $liveObjections,
            'missing_fields' => $missingFields,
            'recommendations' => $this->recommendations($totalSessions, $negativeMessages, $stuckOrFailure, $scriptHotspots, $nodeHotspots, $feedbackTagHotspots, $profileHotspots, $liveObjections, $missingFields),
            'limitations' => $this->limitations(),
        ];
    }

    /**
     * @return Builder<SalesScriptPlaySession>
     */
    private function trainerQuery(
        User $user,
        CarbonImmutable $since,
        bool $canViewAll,
        ?int $filterUserId,
        ?string $profileKey,
        ?int $versionId,
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

        if ($profileKey !== null && $profileKey !== '') {
            $query->where('trainer_profile_key', $profileKey);
        }

        if ($versionId !== null && $versionId > 0) {
            $query->where('sales_script_version_id', $versionId);
        }

        return $query;
    }

    /**
     * @param  Collection<int, int>  $sessionIds
     */
    private function negativeMessageCount(Collection $sessionIds): int
    {
        if ($sessionIds->isEmpty()) {
            return 0;
        }

        return SalesScriptTrainerMessage::query()
            ->whereIn('sales_script_play_session_id', $sessionIds)
            ->where(function (Builder $query): void {
                $query->where('peer_reaction', 'negative')
                    ->orWhere('auto_peer_reaction', 'negative');
            })
            ->count();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $trainerQuery
     * @param  Collection<int, int>  $sessionIds
     * @return list<array<string, mixed>>
     */
    private function scriptHotspots(Builder $trainerQuery, Collection $sessionIds): array
    {
        $rows = (clone $trainerQuery)
            ->toBase()
            ->selectRaw('sales_script_version_id as version_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN trainer_dialog_quality IN (?, ?) THEN 1 ELSE 0 END) as stuck_or_failure', [
                SalesTrainerDialogQuality::Stuck->value,
                SalesTrainerDialogQuality::Failure->value,
            ])
            ->selectRaw('AVG(trainer_score) as avg_score')
            ->groupBy('sales_script_version_id')
            ->orderByDesc('stuck_or_failure')
            ->orderBy('avg_score')
            ->limit(8)
            ->get();

        $labels = SalesScriptPlaySession::query()
            ->whereIn('sales_script_version_id', $rows->pluck('version_id')->filter()->unique())
            ->with('version.script')
            ->get()
            ->unique('sales_script_version_id')
            ->mapWithKeys(function (SalesScriptPlaySession $session): array {
                $version = $session->version;
                $title = $version?->script?->title;

                return [
                    (int) $session->sales_script_version_id => $title
                        ? "{$title} · v{$version?->version_number}"
                        : "v{$version?->version_number}",
                ];
            });

        $negativeByVersion = $this->negativeMessagesByVersion($sessionIds);

        return $rows
            ->map(function (object $row) use ($labels, $negativeByVersion): array {
                $versionId = (int) $row->version_id;
                $total = (int) $row->total;
                $stuckOrFailure = (int) $row->stuck_or_failure;

                return [
                    'version_id' => $versionId,
                    'script_label' => (string) ($labels[$versionId] ?? "version#{$versionId}"),
                    'total' => $total,
                    'stuck_or_failure' => $stuckOrFailure,
                    'stuck_or_failure_rate_pct' => round($stuckOrFailure / max(1, $total) * 100, 1),
                    'negative_messages' => $negativeByVersion[$versionId] ?? 0,
                    'avg_score' => round((float) ($row->avg_score ?? 0), 1),
                ];
            })
            ->filter(fn (array $row): bool => $row['stuck_or_failure'] > 0 || $row['negative_messages'] > 0 || $row['avg_score'] < 60)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $sessionIds
     * @return array<int, int>
     */
    private function negativeMessagesByVersion(Collection $sessionIds): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $rows = SalesScriptTrainerMessage::query()
            ->join('sales_script_play_sessions as s', 's.id', '=', 'sales_script_trainer_messages.sales_script_play_session_id')
            ->whereIn('sales_script_trainer_messages.sales_script_play_session_id', $sessionIds)
            ->where(function (Builder $query): void {
                $query->where('sales_script_trainer_messages.peer_reaction', 'negative')
                    ->orWhere('sales_script_trainer_messages.auto_peer_reaction', 'negative');
            })
            ->toBase()
            ->selectRaw('s.sales_script_version_id as version_id, COUNT(*) as total')
            ->groupBy('s.sales_script_version_id')
            ->get();

        return $rows
            ->mapWithKeys(fn (object $row): array => [(int) $row->version_id => (int) $row->total])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $sessionIds
     * @return list<array<string, mixed>>
     */
    private function nodeHotspots(Collection $sessionIds): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, SalesScriptTrainerMessage> $messages */
        $messages = SalesScriptTrainerMessage::query()
            ->whereIn('sales_script_play_session_id', $sessionIds)
            ->where('role', 'assistant')
            ->where(function (Builder $query): void {
                $query->where('peer_reaction', 'negative')
                    ->orWhere('auto_peer_reaction', 'negative')
                    ->orWhereNotNull('feedback_tags');
            })
            ->with(['scriptNode.version.script', 'session.version.script'])
            ->orderByDesc('id')
            ->limit(600)
            ->get();

        $groups = [];

        foreach ($messages as $message) {
            $key = $message->sales_script_node_id !== null
                ? 'node:'.$message->sales_script_node_id
                : 'step:'.($message->step_key ?: 'unknown');
            $node = $message->scriptNode;
            $session = $message->session;
            $tags = array_values(array_filter(
                (array) ($message->feedback_tags ?? []),
                fn (mixed $tag): bool => is_string($tag) && $tag !== '',
            ));

            $groups[$key] ??= [
                'sales_script_node_id' => $message->sales_script_node_id,
                'step_key' => $message->step_key ?: $node?->client_key,
                'script_label' => $this->scriptLabel($session),
                'node_label' => $node?->client_key ?: ($message->step_key ?: 'Шаг не определён'),
                'node_excerpt' => $node?->body ? Str::limit((string) $node->body, 140) : null,
                'signals' => 0,
                'negative_messages' => 0,
                'tag_counts' => [],
            ];

            $groups[$key]['signals']++;

            if ($message->peer_reaction?->value === 'negative' || $message->auto_peer_reaction?->value === 'negative') {
                $groups[$key]['negative_messages']++;
            }

            foreach ($tags as $tag) {
                $groups[$key]['tag_counts'][$tag] = ($groups[$key]['tag_counts'][$tag] ?? 0) + 1;
            }
        }

        return collect($groups)
            ->map(function (array $row): array {
                arsort($row['tag_counts']);
                $row['top_tags'] = collect($row['tag_counts'])
                    ->take(3)
                    ->map(fn (int $count, string $tag): array => [
                        'tag' => $tag,
                        'label' => $this->feedbackTagLabel($tag),
                        'count' => $count,
                    ])
                    ->values()
                    ->all();
                unset($row['tag_counts']);

                return $row;
            })
            ->sortByDesc(fn (array $row): int => ($row['negative_messages'] * 2) + $row['signals'])
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $sessionIds
     * @return list<array{tag:string,label:string,total:int}>
     */
    private function feedbackTagHotspots(Collection $sessionIds): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $counts = [];

        SalesScriptTrainerMessage::query()
            ->whereIn('sales_script_play_session_id', $sessionIds)
            ->whereNotNull('feedback_tags')
            ->orderByDesc('id')
            ->limit(1000)
            ->get(['feedback_tags'])
            ->each(function (SalesScriptTrainerMessage $message) use (&$counts): void {
                foreach ((array) ($message->feedback_tags ?? []) as $tag) {
                    if (! is_string($tag) || $tag === '') {
                        continue;
                    }

                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            });

        arsort($counts);

        return collect($counts)
            ->take(8)
            ->map(fn (int $total, string $tag): array => [
                'tag' => $tag,
                'label' => $this->feedbackTagLabel($tag),
                'total' => $total,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $trainerQuery
     * @return list<array<string, mixed>>
     */
    private function profileHotspots(Builder $trainerQuery): array
    {
        return (clone $trainerQuery)
            ->toBase()
            ->selectRaw('trainer_profile_key as profile_key')
            ->selectRaw('MAX(trainer_profile_title) as profile_title')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN trainer_dialog_quality IN (?, ?) THEN 1 ELSE 0 END) as stuck_or_failure', [
                SalesTrainerDialogQuality::Stuck->value,
                SalesTrainerDialogQuality::Failure->value,
            ])
            ->groupBy('trainer_profile_key')
            ->orderByDesc('stuck_or_failure')
            ->limit(8)
            ->get()
            ->map(function (object $row): array {
                $total = (int) $row->total;
                $stuckOrFailure = (int) $row->stuck_or_failure;

                return [
                    'profile_key' => $row->profile_key ? (string) $row->profile_key : null,
                    'profile_title' => (string) ($row->profile_title ?: '—'),
                    'total' => $total,
                    'stuck_or_failure' => $stuckOrFailure,
                    'stuck_or_failure_rate_pct' => round($stuckOrFailure / max(1, $total) * 100, 1),
                ];
            })
            ->filter(fn (array $row): bool => $row['stuck_or_failure'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Builder<SalesScriptPlaySession>  $trainerQuery
     * @return list<array{code:string,label:string,missing:int}>
     */
    private function missingFields(Builder $trainerQuery): array
    {
        /** @var Collection<int, SalesScriptPlaySession> $sessions */
        $sessions = (clone $trainerQuery)
            ->with(['version.nodes', 'fieldValues.captureField'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $missing = [];
        $labels = [];

        foreach ($sessions as $session) {
            $expectedCodes = $session->version?->nodes
                ->flatMap(fn ($node) => $node->capture_field_codes ?? [])
                ->filter()
                ->unique()
                ->values()
                ->all() ?? [];

            if ($expectedCodes === []) {
                continue;
            }

            $actualCodes = $session->fieldValues
                ->map(fn ($value) => $value->captureField?->code)
                ->filter()
                ->unique()
                ->flip();

            foreach ($session->fieldValues as $value) {
                if ($value->captureField?->code !== null) {
                    $labels[$value->captureField->code] = $value->captureField->label;
                }
            }

            foreach ($session->version?->nodes ?? [] as $node) {
                foreach ($node->capture_field_codes ?? [] as $code) {
                    $labels[$code] ??= $code;
                }
            }

            foreach ($expectedCodes as $code) {
                if (! $actualCodes->has($code)) {
                    $missing[$code] = ($missing[$code] ?? 0) + 1;
                }
            }
        }

        arsort($missing);

        return collect($missing)
            ->take(8)
            ->map(fn (int $count, string $code): array => [
                'code' => $code,
                'label' => (string) ($labels[$code] ?? $code),
                'missing' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function liveObjections(
        User $user,
        CarbonImmutable $since,
        bool $canViewAll,
        ?int $filterUserId,
        ?int $versionId,
    ): array {
        $query = SalesScriptPlaySession::query()
            ->where('is_trainer', false)
            ->where('created_at', '>=', $since)
            ->whereNotNull('primary_reaction_class_id');

        if ($canViewAll) {
            if ($filterUserId !== null && $filterUserId > 0) {
                $query->where('user_id', $filterUserId);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($versionId !== null && $versionId > 0) {
            $query->where('sales_script_version_id', $versionId);
        }

        $rows = $query
            ->toBase()
            ->selectRaw('primary_reaction_class_id as reaction_class_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('primary_reaction_class_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $classes = SalesScriptReactionClass::query()
            ->whereIn('id', $rows->pluck('reaction_class_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($classes): array {
            $id = (int) $row->reaction_class_id;
            $class = $classes->get($id);

            return [
                'reaction_class_id' => $id,
                'key' => $class?->key,
                'label' => (string) ($class?->label ?? "reaction#{$id}"),
                'total' => (int) $row->total,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $scriptHotspots
     * @param  list<array<string, mixed>>  $nodeHotspots
     * @param  list<array<string, mixed>>  $feedbackTagHotspots
     * @param  list<array<string, mixed>>  $profileHotspots
     * @param  list<array<string, mixed>>  $liveObjections
     * @param  list<array<string, mixed>>  $missingFields
     * @return list<string>
     */
    private function recommendations(
        int $totalSessions,
        int $negativeMessages,
        int $stuckOrFailure,
        array $scriptHotspots,
        array $nodeHotspots,
        array $feedbackTagHotspots,
        array $profileHotspots,
        array $liveObjections,
        array $missingFields,
    ): array {
        $recommendations = [];

        if ($stuckOrFailure > 0) {
            $recommendations[] = 'Проверьте первые шаги сценариев с тупиками/неудачами: обычно там слишком широкий вопрос или слишком ранний питч.';
        }

        if ($negativeMessages > 0) {
            $recommendations[] = 'Есть негативные оценки ответов ассистента: усилите контекст профиля и подсказку текущего узла, чтобы модель отвечала уже и предметнее.';
        }

        if ($scriptHotspots !== []) {
            $recommendations[] = 'Начните редактуру со сценария: '.$scriptHotspots[0]['script_label'].'. Там больше всего сигналов “не помогло”.';
        }

        if ($nodeHotspots !== []) {
            $recommendations[] = 'Первый кандидат на правку внутри сценария: шаг “'.$nodeHotspots[0]['node_label'].'” в “'.$nodeHotspots[0]['script_label'].'”. Проверьте текст узла, hint и варианты реакции клиента.';
        }

        if ($feedbackTagHotspots !== []) {
            $recommendations[] = 'Самая частая причина оценки: “'.$feedbackTagHotspots[0]['label'].'”. Это хороший критерий для следующей ручной редакции сценариев.';
        }

        if ($profileHotspots !== []) {
            $recommendations[] = 'Профиль “'.$profileHotspots[0]['profile_title'].'” чаще других приводит к тупикам: проверьте, достаточно ли конкретны его цель и условия согласия.';
        }

        if ($liveObjections !== []) {
            $recommendations[] = 'В живых скриптах чаще всего встречается возражение “'.$liveObjections[0]['label'].'”: убедитесь, что тренажёр отрабатывает эту ветку.';
        }

        if ($missingFields !== []) {
            $recommendations[] = 'Чаще всего не фиксируется поле “'.$missingFields[0]['label'].'”: сделайте вопрос по нему отдельным и видимым в телесуфлёре.';
        }

        if ($recommendations === []) {
            $recommendations[] = $totalSessions >= 5
                ? 'Критичных сигналов нет: продолжайте копить оценки и сверяйте живые возражения с тренажёрными ветками.'
                : 'Данных мало: проведите ещё несколько тренировок и проставьте оценки реплик ассистента.';
        }

        return $recommendations;
    }

    /**
     * @return list<string>
     */
    private function limitations(): array
    {
        return [
            'Исторические сообщения без node_id будут попадать только в агрегаты по сценарию/профилю, пока не накопятся новые оценки.',
            'Рекомендации не применяются автоматически: редактор сценария должен подтвердить правку вручную.',
        ];
    }

    private function scriptLabel(?SalesScriptPlaySession $session): string
    {
        $version = $session?->version;
        $title = $version?->script?->title;

        return $title
            ? "{$title} · v{$version?->version_number}"
            : ($version?->version_number ? "v{$version->version_number}" : 'Сценарий не определён');
    }

    private function feedbackTagLabel(string $tag): string
    {
        return match ($tag) {
            'useful_next_step' => 'ясный следующий шаг',
            'useful_objection' => 'сняло возражение',
            'useful_question' => 'хороший вопрос',
            'useful_wording' => 'удачная формулировка',
            'bad_too_generic' => 'слишком общо',
            'bad_wrong_stage' => 'не тот этап',
            'bad_missed_objection' => 'мимо возражения',
            'bad_not_actionable' => 'нет действия',
            'bad_too_long' => 'слишком длинно',
            default => $tag,
        };
    }
}
