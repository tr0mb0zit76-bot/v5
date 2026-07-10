<?php

namespace App\Services\Reports;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadBusinessProcessService;
use App\Support\LeadViewAuthorization;
use Illuminate\Database\Eloquent\Builder;

class LeadProcessReportsService
{
    public const STUCK_STAGE_DAYS = 3;

    public function __construct(
        private readonly LeadBusinessProcessService $leadBusinessProcessService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function stageSlaBreached(?User $viewer = null, ?int $filterResponsibleId = null): array
    {
        if (! $this->leadBusinessProcessService->tablesReady()) {
            return [];
        }

        return $this->activeProcessLeadsQuery($viewer, $filterResponsibleId)
            ->whereNotNull('stage_due_at')
            ->where('stage_due_at', '<', now())
            ->whereHas('businessProcessStage', fn ($query) => $query->where('is_terminal', false))
            ->orderBy('stage_due_at')
            ->get()
            ->map(fn (Lead $lead): array => $this->serializeReportRow($lead, true))
            ->values()
            ->all();
    }

    /**
     * Объединённый отчёт: просрочен calendar due и/или долго на этапе без перехода.
     *
     * @return array{rows: list<array<string, mixed>>, stuck_days: int}
     */
    public function processStageIssues(?User $viewer = null, int $minStuckDays = self::STUCK_STAGE_DAYS, ?int $filterResponsibleId = null): array
    {
        $minStuckDays = max(1, min(365, $minStuckDays));

        $byLeadId = [];

        foreach ($this->stageSlaBreached($viewer, $filterResponsibleId) as $row) {
            $id = (int) $row['lead_id'];
            $byLeadId[$id] = $row;
            $byLeadId[$id]['issue_flags'] = ['due_overdue'];
        }

        foreach ($this->stuckOnStage($viewer, $minStuckDays, $filterResponsibleId) as $row) {
            $id = (int) $row['lead_id'];
            if (isset($byLeadId[$id])) {
                $byLeadId[$id]['days_on_stage'] = $row['days_on_stage'];
                $byLeadId[$id]['stage_entered_at'] = $row['stage_entered_at'];
                $byLeadId[$id]['issue_flags'] = ['due_overdue', 'stuck'];
            } else {
                $byLeadId[$id] = $row;
                $byLeadId[$id]['issue_flags'] = ['stuck'];
            }
        }

        $rows = collect($byLeadId)
            ->map(function (array $row): array {
                $flags = $row['issue_flags'] ?? [];

                return [
                    ...$row,
                    'issue_labels' => $this->issueLabels($flags),
                ];
            })
            ->sortBy([
                fn (array $row): int => in_array('due_overdue', $row['issue_flags'] ?? [], true) ? 0 : 1,
                fn (array $row): int => -(int) ($row['days_overdue'] ?? 0),
                fn (array $row): int => -(int) ($row['days_on_stage'] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'stuck_days' => $minStuckDays,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stuckOnStage(?User $viewer = null, int $minDays = self::STUCK_STAGE_DAYS, ?int $filterResponsibleId = null): array
    {
        if (! $this->leadBusinessProcessService->tablesReady()) {
            return [];
        }

        $threshold = now()->subDays(max(1, $minDays));

        return $this->activeProcessLeadsQuery($viewer, $filterResponsibleId)
            ->whereNotNull('stage_entered_at')
            ->where('stage_entered_at', '<', $threshold)
            ->whereHas('businessProcessStage', fn ($query) => $query->where('is_terminal', false))
            ->orderBy('stage_entered_at')
            ->get()
            ->map(fn (Lead $lead): array => $this->serializeReportRow($lead, false))
            ->values()
            ->all();
    }

    /**
     * @return Builder<Lead>
     */
    private function activeProcessLeadsQuery(?User $viewer, ?int $filterResponsibleId = null)
    {
        $query = Lead::query()
            ->with([
                'businessProcess:id,name',
                'businessProcessStage:id,name,is_terminal,duration_days',
                'responsible:id,name',
            ])
            ->whereNotNull('business_process_id')
            ->whereNotIn('status', ['won', 'lost']);

        if ($viewer !== null) {
            LeadViewAuthorization::applyLeadsVisibilityScope($query, $viewer);
        }

        if ($filterResponsibleId !== null) {
            $query->where('responsible_id', $filterResponsibleId);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReportRow(Lead $lead, bool $slaFocus): array
    {
        $daysOnStage = $lead->stage_entered_at !== null
            ? (int) $lead->stage_entered_at->diffInDays(now())
            : null;

        $daysOverdue = $lead->stage_due_at !== null && $lead->stage_due_at->isPast()
            ? (int) $lead->stage_due_at->diffInDays(now())
            : null;

        return [
            'lead_id' => $lead->id,
            'lead_number' => $lead->number,
            'lead_title' => $lead->title,
            'responsible_name' => $lead->responsible?->name,
            'process_name' => $lead->businessProcess?->name,
            'stage_name' => $lead->businessProcessStage?->name,
            'stage_entered_at' => optional($lead->stage_entered_at)?->toIso8601String(),
            'stage_due_at' => optional($lead->stage_due_at)?->toIso8601String(),
            'days_on_stage' => $daysOnStage,
            'days_overdue' => $daysOverdue,
            'is_stage_overdue' => $this->leadBusinessProcessService->isStageOverdue($lead),
            'sla_focus' => $slaFocus,
        ];
    }

    /**
     * @param  list<string>  $flags
     * @return list<string>
     */
    private function issueLabels(array $flags): array
    {
        $labels = [];

        if (in_array('due_overdue', $flags, true)) {
            $labels[] = 'Срок этапа';
        }

        if (in_array('stuck', $flags, true)) {
            $labels[] = 'Долго на этапе';
        }

        return $labels;
    }
}
