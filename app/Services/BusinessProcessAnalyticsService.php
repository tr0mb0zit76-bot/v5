<?php

namespace App\Services;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\LeadProcessStageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Аналитика воронок: SLA, узкие места, конверсия — для «коуча бизнеса».
 */
class BusinessProcessAnalyticsService
{
    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
    ) {}

    public function tablesReady(): bool
    {
        return $this->leadBusinessProcessService->tablesReady()
            && Schema::hasTable('lead_process_stage_logs');
    }

    /**
     * @return array{
     *     lookback_days: int,
     *     processes: list<array<string, mixed>>,
     *     recommendations: list<array{process_id: int, process_name: string, severity: string, message: string}>
     * }
     */
    public function healthOverview(int $lookbackDays = 90): array
    {
        $lookbackDays = max(7, min(365, $lookbackDays));

        if (! $this->tablesReady()) {
            return [
                'lookback_days' => $lookbackDays,
                'processes' => [],
                'recommendations' => [],
            ];
        }

        $since = now()->subDays($lookbackDays);

        $processes = BusinessProcess::query()
            ->with(['stages' => fn ($query) => $query->orderBy('sequence')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activeCounts = $this->activeLeadCountsByStage();
        $logStats = $this->stageLogStatsSince($since);
        $outcomes = $this->terminalOutcomesSince($since);

        $serialized = [];
        $recommendations = [];

        foreach ($processes as $process) {
            $stagesPayload = [];
            $maxAvgDays = 0.0;
            $bottleneckStage = null;

            foreach ($process->stages as $stage) {
                $stageId = (int) $stage->id;
                $stats = $logStats[$stageId] ?? [
                    'entries' => 0,
                    'completed' => 0,
                    'avg_hours' => null,
                    'sla_breaches' => 0,
                ];

                $avgDays = $stats['avg_hours'] !== null ? round($stats['avg_hours'] / 24, 1) : null;
                $normDays = (int) ($stage->duration_days ?? 0);
                $active = (int) ($activeCounts[$stageId] ?? 0);

                $conversionNext = $this->stageConversionRate($process, $stage, $since);

                $stagesPayload[] = [
                    'id' => $stageId,
                    'name' => $stage->name,
                    'sequence' => $stage->sequence,
                    'is_terminal' => $stage->is_terminal,
                    'terminal_outcome' => $stage->terminal_outcome,
                    'duration_days_norm' => $normDays,
                    'active_leads' => $active,
                    'entries_in_period' => $stats['entries'],
                    'completed_in_period' => $stats['completed'],
                    'avg_days_on_stage' => $avgDays,
                    'sla_breaches_in_period' => $stats['sla_breaches'],
                    'conversion_to_next_percent' => $conversionNext,
                    'has_playbook' => filled($stage->description) || filled($stage->stage_goal),
                ];

                if (! $stage->is_terminal && $avgDays !== null && $avgDays > $maxAvgDays) {
                    $maxAvgDays = $avgDays;
                    $bottleneckStage = $stage;
                }

                if (! $stage->is_terminal && $normDays > 0 && $avgDays !== null && $avgDays > $normDays * 1.5) {
                    $recommendations[] = [
                        'process_id' => $process->id,
                        'process_name' => $process->name,
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Этап «%s»: среднее время %.1f дн. при нормативе %d дн. — пересмотрите playbook или SLA.',
                            $stage->name,
                            $avgDays,
                            $normDays,
                        ),
                    ];
                }

                if (! $stage->is_terminal && ! filled($stage->description) && ! filled($stage->stage_goal)) {
                    $recommendations[] = [
                        'process_id' => $process->id,
                        'process_name' => $process->name,
                        'severity' => 'info',
                        'message' => sprintf(
                            'Этап «%s» без инструкции для менеджера — добавьте цель и чек-лист действий.',
                            $stage->name,
                        ),
                    ];
                }
            }

            $won = (int) ($outcomes[$process->id]['won'] ?? 0);
            $lost = (int) ($outcomes[$process->id]['lost'] ?? 0);
            $closed = $won + $lost;
            $winRate = $closed > 0 ? (int) round(($won / $closed) * 100) : null;

            if ($bottleneckStage !== null && $maxAvgDays >= 2) {
                $recommendations[] = [
                    'process_id' => $process->id,
                    'process_name' => $process->name,
                    'severity' => 'focus',
                    'message' => sprintf(
                        'Узкое место: «%s» (в среднем %.1f дн.) — начните улучшение воронки отсюда.',
                        $bottleneckStage->name,
                        $maxAvgDays,
                    ),
                ];
            }

            $serialized[] = [
                'id' => $process->id,
                'name' => $process->name,
                'slug' => $process->slug,
                'win_rate_percent' => $winRate,
                'closed_in_period' => $closed,
                'won_in_period' => $won,
                'lost_in_period' => $lost,
                'stages' => $stagesPayload,
            ];
        }

        usort($recommendations, function (array $a, array $b): int {
            $order = ['focus' => 0, 'warning' => 1, 'info' => 2];

            return ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9);
        });

        return [
            'lookback_days' => $lookbackDays,
            'processes' => $serialized,
            'recommendations' => array_slice($recommendations, 0, 12),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function activeLeadCountsByStage(): array
    {
        return Lead::query()
            ->whereNotNull('business_process_id')
            ->whereNotNull('business_process_stage_id')
            ->whereNotIn('status', ['won', 'lost'])
            ->select('business_process_stage_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('business_process_stage_id')
            ->pluck('aggregate', 'business_process_stage_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<int, array{entries: int, completed: int, avg_hours: ?float, sla_breaches: int}>
     */
    private function stageLogStatsSince(Carbon $since): array
    {
        /** @var Collection<int, LeadProcessStageLog> $logs */
        $logs = LeadProcessStageLog::query()
            ->where('entered_at', '>=', $since)
            ->get(['business_process_stage_id', 'entered_at', 'exited_at', 'due_at']);

        $out = [];

        foreach ($logs as $log) {
            $stageId = (int) $log->business_process_stage_id;

            if (! isset($out[$stageId])) {
                $out[$stageId] = [
                    'entries' => 0,
                    'completed' => 0,
                    'duration_hours_sum' => 0.0,
                    'duration_samples' => 0,
                    'sla_breaches' => 0,
                ];
            }

            $out[$stageId]['entries']++;

            if ($log->exited_at !== null) {
                $out[$stageId]['completed']++;
                $hours = $log->entered_at->diffInMinutes($log->exited_at) / 60;
                $out[$stageId]['duration_hours_sum'] += $hours;
                $out[$stageId]['duration_samples']++;

                if ($log->due_at !== null && $log->exited_at->gt($log->due_at)) {
                    $out[$stageId]['sla_breaches']++;
                }
            } elseif ($log->due_at !== null && $log->due_at->isPast()) {
                $out[$stageId]['sla_breaches']++;
            }
        }

        $normalized = [];
        foreach ($out as $stageId => $row) {
            $normalized[$stageId] = [
                'entries' => $row['entries'],
                'completed' => $row['completed'],
                'avg_hours' => $row['duration_samples'] > 0
                    ? $row['duration_hours_sum'] / $row['duration_samples']
                    : null,
                'sla_breaches' => $row['sla_breaches'],
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{won: int, lost: int}>
     */
    private function terminalOutcomesSince(Carbon $since): array
    {
        if (! Schema::hasColumn('leads', 'business_process_id')) {
            return [];
        }

        $rows = Lead::query()
            ->whereNotNull('business_process_id')
            ->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', $since)
            ->select('business_process_id', 'status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('business_process_id', 'status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $processId = (int) $row->business_process_id;
            if (! isset($out[$processId])) {
                $out[$processId] = ['won' => 0, 'lost' => 0];
            }

            if ($row->status === 'won') {
                $out[$processId]['won'] = (int) $row->aggregate;
            } elseif ($row->status === 'lost') {
                $out[$processId]['lost'] = (int) $row->aggregate;
            }
        }

        return $out;
    }

    private function stageConversionRate(BusinessProcess $process, BusinessProcessStage $stage, Carbon $since): ?int
    {
        if ($stage->is_terminal) {
            return null;
        }

        $nextStage = $process->stages
            ->first(fn (BusinessProcessStage $candidate): bool => (int) $candidate->sequence > (int) $stage->sequence && ! $candidate->is_terminal);

        if ($nextStage === null) {
            $nextStage = $process->stages
                ->first(fn (BusinessProcessStage $candidate): bool => (int) $candidate->sequence > (int) $stage->sequence);
        }

        if ($nextStage === null) {
            return null;
        }

        $entered = LeadProcessStageLog::query()
            ->where('business_process_stage_id', $stage->id)
            ->where('entered_at', '>=', $since)
            ->distinct('lead_id')
            ->count('lead_id');

        if ($entered === 0) {
            return null;
        }

        $advanced = LeadProcessStageLog::query()
            ->where('business_process_stage_id', $nextStage->id)
            ->where('entered_at', '>=', $since)
            ->whereIn('lead_id', function ($query) use ($stage, $since): void {
                $query->select('lead_id')
                    ->from('lead_process_stage_logs')
                    ->where('business_process_stage_id', $stage->id)
                    ->where('entered_at', '>=', $since);
            })
            ->distinct('lead_id')
            ->count('lead_id');

        return (int) round(min(100, ($advanced / $entered) * 100));
    }
}
