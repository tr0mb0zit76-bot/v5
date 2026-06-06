<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiInteractionEventType;
use App\Support\AiInteractionFeature;
use App\Support\AiInteractionOutcome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiUsageAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function insights(int $days = 30, int $topLimit = 20, int $sampleLimit = 15): array
    {
        if (! Schema::hasTable('ai_interaction_events')) {
            return [
                'available' => false,
                'message' => 'Таблица ai_interaction_events ещё не создана. Выполните php artisan migrate.',
            ];
        }

        $days = max(1, min(365, $days));
        $topLimit = max(5, min(50, $topLimit));
        $sampleLimit = max(5, min(50, $sampleLimit));

        $since = Carbon::now()->subDays($days);

        $turnsQuery = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('created_at', '>=', $since);

        $outcomeCounts = (clone $turnsQuery)
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->pluck('total', 'outcome')
            ->all();

        $weakOutcomes = [
            AiInteractionOutcome::WeakAnswer->value,
            AiInteractionOutcome::Failed->value,
        ];
        $weakInList = "'".implode("','", $weakOutcomes)."'";

        $topQuestions = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('created_at', '>=', $since)
            ->whereNotNull('prompt_fingerprint')
            ->select(
                'prompt_fingerprint',
                DB::raw('COUNT(*) as ask_count'),
                DB::raw("SUM(CASE WHEN outcome IN ({$weakInList}) THEN 1 ELSE 0 END) as weak_or_failed_count"),
                DB::raw('MAX(user_prompt_redacted) as sample_prompt'),
            )
            ->groupBy('prompt_fingerprint')
            ->orderByDesc('ask_count')
            ->limit($topLimit)
            ->get()
            ->map(fn ($row) => [
                'ask_count' => (int) $row->ask_count,
                'weak_or_failed_count' => (int) $row->weak_or_failed_count,
                'sample_prompt' => (string) ($row->sample_prompt ?? ''),
            ])
            ->values()
            ->all();

        $recentWeak = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('created_at', '>=', $since)
            ->whereIn('outcome', [
                AiInteractionOutcome::WeakAnswer->value,
                AiInteractionOutcome::Failed->value,
                AiInteractionOutcome::Unavailable->value,
            ])
            ->orderByDesc('created_at')
            ->limit($sampleLimit)
            ->get([
                'created_at',
                'feature',
                'outcome',
                'user_prompt_redacted',
                'assistant_reply_redacted',
                'tool_rounds',
                'tools_used',
            ])
            ->map(fn ($row) => [
                'at' => (string) $row->created_at,
                'feature' => (string) $row->feature,
                'outcome' => (string) $row->outcome,
                'user_prompt' => (string) ($row->user_prompt_redacted ?? ''),
                'assistant_reply' => (string) ($row->assistant_reply_redacted ?? ''),
                'tool_rounds' => $row->tool_rounds !== null ? (int) $row->tool_rounds : null,
                'tools_used' => $row->tools_used ? json_decode((string) $row->tools_used, true) : [],
            ])
            ->values()
            ->all();

        $toolStats = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::ToolInvoked->value)
            ->where('created_at', '>=', $since)
            ->whereNotNull('tool_name')
            ->select(
                'tool_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) as error_count'),
            )
            ->groupBy('tool_name')
            ->orderByDesc('total')
            ->limit($topLimit)
            ->get()
            ->map(fn ($row) => [
                'tool' => (string) $row->tool_name,
                'total' => (int) $row->total,
                'error_count' => (int) $row->error_count,
            ])
            ->values()
            ->all();

        $intakeStats = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::IntakeExtracted->value)
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) as failed')
            ->first();

        $featureBreakdown = (clone $turnsQuery)
            ->select('feature', DB::raw('COUNT(*) as total'))
            ->groupBy('feature')
            ->pluck('total', 'feature')
            ->all();

        $dailyTurns = DB::table('ai_interaction_events')
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $weakTotal = (int) (($outcomeCounts[AiInteractionOutcome::WeakAnswer->value] ?? 0)
            + ($outcomeCounts[AiInteractionOutcome::Failed->value] ?? 0)
            + ($outcomeCounts[AiInteractionOutcome::Unavailable->value] ?? 0));

        $salesBookGaps = $this->salesBookKnowledgeGaps($since, $sampleLimit);
        $commandBarFeedback = $this->commandBarFeedbackSummary($since, $sampleLimit);

        return [
            'available' => true,
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'conversation_turns_total' => array_sum(array_map('intval', $outcomeCounts)),
            'outcomes' => $outcomeCounts,
            'features' => $featureBreakdown,
            'daily_conversation_turns' => $dailyTurns,
            'weak_or_failed_turns' => $weakTotal,
            'top_user_questions' => $topQuestions,
            'recent_weak_or_failed' => $recentWeak,
            'tool_usage' => $toolStats,
            'order_intake' => [
                'total' => (int) ($intakeStats->total ?? 0),
                'failed' => (int) ($intakeStats->failed ?? 0),
            ],
            'knowledge_gap_hints' => $this->knowledgeGapHints($topQuestions, $recentWeak, $salesBookGaps, $commandBarFeedback),
            'sales_book_knowledge_gaps' => $salesBookGaps,
            'command_bar_feedback' => $commandBarFeedback,
        ];
    }

    /**
     * @param  list<array{ask_count: int, weak_or_failed_count: int, sample_prompt: string}>  $topQuestions
     * @param  list<array<string, mixed>>  $recentWeak
     * @param  list<array<string, mixed>>  $salesBookGaps
     * @param  array<string, mixed>  $commandBarFeedback
     * @return list<string>
     */
    private function knowledgeGapHints(array $topQuestions, array $recentWeak, array $salesBookGaps, array $commandBarFeedback): array
    {
        $hints = [];

        $gaps = collect($topQuestions)
            ->filter(fn (array $row): bool => $row['weak_or_failed_count'] > 0 && $row['ask_count'] >= 2)
            ->take(5);

        if ($gaps->isNotEmpty()) {
            $hints[] = 'Есть повторяющиеся вопросы с слабым или неуспешным ответом — добавьте статьи в Книгу продаж (инструмент upsert_sales_book_article) или уточните системный промпт ассистента.';
        }

        if (count($salesBookGaps) >= 3) {
            $hints[] = 'Накопились обращения, где ассистент не нашёл ответ в Книге продаж — приоритетно дополните соответствующие страницы.';
        }

        if (($commandBarFeedback['not_helpful'] ?? 0) >= 3) {
            $hints[] = 'Есть отрицательные оценки ответов ассистента — просмотрите command_bar_feedback и усильте статьи по этим темам.';
        }

        if (count($recentWeak) >= 5) {
            $hints[] = 'За период накопилось несколько неудачных диалогов — просмотрите recent_weak_or_failed и сопоставьте с пробелами в базе знаний.';
        }

        if ($hints === []) {
            $hints[] = 'Явных пробелов по агрегатам не видно; при появлении новых тем повторите анализ через несколько дней.';
        }

        return $hints;
    }

    public function dismissSalesBookGap(int $eventId, User $user): bool
    {
        if (! Schema::hasTable('ai_interaction_events')) {
            return false;
        }

        $row = DB::table('ai_interaction_events')
            ->where('id', $eventId)
            ->where('feature', AiInteractionFeature::CommandBar->value)
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('metadata->sales_book->gap', true)
            ->where(function ($query): void {
                $query->whereNull('metadata->sales_book->gap_dismissed')
                    ->orWhere('metadata->sales_book->gap_dismissed', false);
            })
            ->first(['id', 'metadata']);

        if ($row === null) {
            return false;
        }

        $metadata = json_decode((string) ($row->metadata ?? ''), true);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $salesBook = is_array($metadata['sales_book'] ?? null) ? $metadata['sales_book'] : [];
        $salesBook['gap'] = false;
        $salesBook['gap_dismissed'] = true;
        $salesBook['gap_dismissed_at'] = now()->toIso8601String();
        $salesBook['gap_dismissed_by'] = $user->id;
        $metadata['sales_book'] = $salesBook;

        DB::table('ai_interaction_events')
            ->where('id', $eventId)
            ->update([
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesBookKnowledgeGaps(Carbon $since, int $limit): array
    {
        return DB::table('ai_interaction_events')
            ->where('feature', AiInteractionFeature::CommandBar->value)
            ->where('event_type', AiInteractionEventType::ConversationTurn->value)
            ->where('created_at', '>=', $since)
            ->where('metadata->sales_book->gap', true)
            ->where(function ($query): void {
                $query->whereNull('metadata->sales_book->gap_dismissed')
                    ->orWhere('metadata->sales_book->gap_dismissed', false);
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'created_at',
                'user_prompt_redacted',
                'outcome',
                'metadata',
            ])
            ->map(function ($row): array {
                $metadata = json_decode((string) ($row->metadata ?? ''), true);

                return [
                    'id' => (int) $row->id,
                    'at' => (string) $row->created_at,
                    'user_prompt' => (string) ($row->user_prompt_redacted ?? ''),
                    'outcome' => (string) ($row->outcome ?? ''),
                    'gap_reason' => is_array($metadata['sales_book'] ?? null)
                        ? ($metadata['sales_book']['gap_reason'] ?? null)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     helpful: int,
     *     not_helpful: int,
     *     recent: list<array<string, mixed>>
     * }
     */
    private function commandBarFeedbackSummary(Carbon $since, int $limit): array
    {
        $base = DB::table('ai_interaction_events')
            ->where('feature', AiInteractionFeature::CommandBar->value)
            ->where('event_type', AiInteractionEventType::UserFeedback->value)
            ->where('created_at', '>=', $since);

        $helpful = (int) (clone $base)->where('metadata->rating', 'helpful')->count();
        $notHelpful = (int) (clone $base)->where('metadata->rating', 'not_helpful')->count();

        $recent = (clone $base)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['created_at', 'metadata'])
            ->map(function ($row): array {
                $metadata = json_decode((string) ($row->metadata ?? ''), true);

                return [
                    'at' => (string) $row->created_at,
                    'rating' => is_array($metadata) ? ($metadata['rating'] ?? null) : null,
                    'comment' => is_array($metadata) ? ($metadata['comment'] ?? null) : null,
                    'user_prompt' => is_array($metadata) ? ($metadata['user_prompt_redacted'] ?? null) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'recent' => $recent,
        ];
    }
}
