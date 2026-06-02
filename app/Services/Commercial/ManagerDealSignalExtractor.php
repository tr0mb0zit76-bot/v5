<?php

namespace App\Services\Commercial;

use App\Models\ActivityEvent;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadProcessStageLog;
use App\Models\Task;
use App\Services\ActivityLedgerService;
use App\Support\ActivityEventType;
use App\Support\LeadCloseOutcomeFlagCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Снимок сигналов по лиду для Outcome Intelligence.
 * Долгое время на этапе без активности ≠ «качественная подготовка» — учитываем activity_count.
 */
final class ManagerDealSignalExtractor
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(Lead $lead): array
    {
        $lead->loadMissing(['businessProcessStage']);

        $qualification = is_array($lead->lead_qualification) ? $lead->lead_qualification : [];
        $gaps = $this->hygieneGaps($lead, $qualification);
        $stagePatterns = $this->stageActivityPatterns($lead);

        $outcome = in_array($lead->status, ['won', 'lost'], true) ? $lead->status : null;

        return [
            'lead_id' => $lead->id,
            'lead_number' => $lead->number,
            'title' => $lead->title,
            'status' => $lead->status,
            'outcome' => $outcome,
            'close_outcome_primary_flag' => $lead->close_outcome_primary_flag,
            'close_outcome_primary_label' => LeadCloseOutcomeFlagCatalog::label($lead->close_outcome_primary_flag),
            'hygiene_score' => $this->hygieneScore($gaps),
            'hygiene_gaps' => $gaps,
            'stage_patterns' => $stagePatterns,
            'has_idle_qualification_dwell' => $this->hasIdleQualificationDwell($stagePatterns),
            'proposal_sent' => $lead->proposal_sent_at !== null,
            'has_next_contact' => $lead->next_contact_at !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $qualification
     * @return list<string>
     */
    private function hygieneGaps(Lead $lead, array $qualification): array
    {
        $gaps = [];

        if (! filled($qualification['need'] ?? null)) {
            $gaps[] = 'no_need';
        }

        if (! filled($qualification['authority'] ?? null)) {
            $gaps[] = 'no_authority';
        }

        if (! filled($qualification['timeline'] ?? null)) {
            $gaps[] = 'no_timeline';
        }

        if ($lead->proposal_sent_at === null) {
            $gaps[] = 'no_proposal_sent';
        }

        if ($lead->next_contact_at === null && ! in_array($lead->status, ['won', 'lost'], true)) {
            $gaps[] = 'no_next_contact';
        }

        return $gaps;
    }

    /**
     * @param  list<string>  $gaps
     */
    private function hygieneScore(array $gaps): int
    {
        $weights = [
            'no_need' => 20,
            'no_authority' => 25,
            'no_timeline' => 15,
            'no_proposal_sent' => 20,
            'no_next_contact' => 10,
        ];

        $deduction = 0;

        foreach ($gaps as $gap) {
            $deduction += $weights[$gap] ?? 5;
        }

        return max(0, 100 - min(100, $deduction));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stageActivityPatterns(Lead $lead): array
    {
        if (! Schema::hasTable('lead_process_stage_logs')) {
            return [];
        }

        $minDays = (float) config('outcome_intelligence.idle_dwell_min_days', 2);
        $maxActivity = (int) config('outcome_intelligence.idle_dwell_max_activity_events', 1);
        $qualPatterns = config('outcome_intelligence.qualification_stage_name_patterns', []);

        /** @var Collection<int, LeadProcessStageLog> $logs */
        $logs = LeadProcessStageLog::query()
            ->where('lead_id', $lead->id)
            ->with('stage:id,name')
            ->orderBy('entered_at')
            ->get();

        $patterns = [];

        foreach ($logs as $log) {
            if ($log->entered_at === null) {
                continue;
            }

            $end = $log->exited_at ?? now();
            $dwellDays = round($log->entered_at->diffInMinutes($end) / 1440, 1);
            $activityCount = $this->activityCountBetween($lead, $log->entered_at, $end);
            $stageName = (string) ($log->stage?->name ?? '');

            $pattern = 'normal';

            if ($dwellDays >= $minDays && $activityCount <= $maxActivity) {
                $pattern = 'idle_dwell';
            } elseif ($activityCount >= 3) {
                $pattern = 'active_work';
            }

            $isQualification = $this->matchesStagePattern($stageName, $qualPatterns);

            $patterns[] = [
                'stage_name' => $stageName,
                'dwell_days' => $dwellDays,
                'activity_count' => $activityCount,
                'pattern' => $pattern,
                'is_qualification_stage' => $isQualification,
            ];
        }

        return $patterns;
    }

    /**
     * @param  list<array<string, mixed>>  $stagePatterns
     */
    private function hasIdleQualificationDwell(array $stagePatterns): bool
    {
        foreach ($stagePatterns as $row) {
            if (($row['is_qualification_stage'] ?? false) && ($row['pattern'] ?? '') === 'idle_dwell') {
                return true;
            }
        }

        return false;
    }

    private function activityCountBetween(Lead $lead, CarbonImmutable|Carbon $from, CarbonImmutable|Carbon $to): int
    {
        $count = 0;

        if ($this->activityLedger->tablesReady()) {
            $count += ActivityEvent::query()
                ->where('subject_type', $lead->getMorphClass())
                ->where('subject_id', $lead->id)
                ->where('event_type', '!=', ActivityEventType::ProcessStageChanged->value)
                ->whereBetween('occurred_at', [$from, $to])
                ->count();
        }

        if (Schema::hasTable('lead_activities')) {
            $count += LeadActivity::query()
                ->where('lead_id', $lead->id)
                ->where('type', '!=', 'status_change')
                ->whereBetween('created_at', [$from, $to])
                ->count();
        }

        if (Schema::hasTable('tasks')) {
            $count += Task::query()
                ->where('lead_id', $lead->id)
                ->whereBetween('created_at', [$from, $to])
                ->count();
        }

        return $count;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesStagePattern(string $stageName, array $patterns): bool
    {
        $lower = mb_strtolower($stageName);

        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_contains($lower, mb_strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
