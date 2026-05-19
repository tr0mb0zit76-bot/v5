<?php

namespace App\Services\Reports;

use App\Models\Lead;
use App\Services\LeadBusinessProcessService;
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
    public function stageSlaBreached(?int $responsibleId = null): array
    {
        if (! $this->leadBusinessProcessService->tablesReady()) {
            return [];
        }

        return $this->activeProcessLeadsQuery($responsibleId)
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
     * @return list<array<string, mixed>>
     */
    public function stuckOnStage(?int $responsibleId = null, int $minDays = self::STUCK_STAGE_DAYS): array
    {
        if (! $this->leadBusinessProcessService->tablesReady()) {
            return [];
        }

        $threshold = now()->subDays(max(1, $minDays));

        return $this->activeProcessLeadsQuery($responsibleId)
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
    private function activeProcessLeadsQuery(?int $responsibleId)
    {
        return Lead::query()
            ->with([
                'businessProcess:id,name',
                'businessProcessStage:id,name,is_terminal,duration_days',
                'responsible:id,name',
            ])
            ->whereNotNull('business_process_id')
            ->whereNotIn('status', ['won', 'lost'])
            ->when($responsibleId !== null, fn ($query) => $query->where('responsible_id', $responsibleId));
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
}
