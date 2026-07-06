<?php

namespace App\Services;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\LeadProcessStageLog;
use App\Models\SalesScriptVersion;
use App\Models\Task;
use App\Models\User;
use App\Support\ActivityEventType;
use App\Support\BusinessProcessPlaybook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadBusinessProcessService
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('business_processes')
            && Schema::hasTable('business_process_stages')
            && Schema::hasColumn('leads', 'business_process_id');
    }

    /**
     * @return Collection<int, BusinessProcess>
     */
    public function activeProcessesWithStages(): Collection
    {
        if (! $this->tablesReady()) {
            return collect();
        }

        return BusinessProcess::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['stages' => fn ($query) => $query->orderBy('sequence')])
            ->get();
    }

    public function startProcess(Lead $lead, BusinessProcess $process, ?User $user = null): void
    {
        $firstStage = $process->stages()->orderBy('sequence')->first();

        abort_if($firstStage === null, 422, 'У бизнес-процесса нет этапов.');

        $this->moveLeadToStage($lead, $firstStage, $user, true);
    }

    public function moveLeadToStage(Lead $lead, BusinessProcessStage $stage, ?User $user = null, bool $isProcessStart = false): int
    {
        abort_if($lead->business_process_id !== null && (int) $lead->business_process_id !== (int) $stage->business_process_id, 422, 'Этап не относится к процессу лида.');

        $now = now();

        if ($isProcessStart || $lead->business_process_id === null) {
            $lead->business_process_id = $stage->business_process_id;
            $lead->process_started_at = $now;
        }

        if ($lead->business_process_stage_id !== null && (int) $lead->business_process_stage_id !== (int) $stage->id) {
            $this->closeOpenLog($lead, $now);
        }

        $lead->business_process_stage_id = $stage->id;
        $lead->stage_entered_at = $now;
        $lead->stage_due_at = $this->resolveStageDueAt($stage, $now);
        $lead->save();

        $log = LeadProcessStageLog::query()->create([
            'lead_id' => $lead->id,
            'business_process_stage_id' => $stage->id,
            'entered_at' => $now,
            'due_at' => $lead->stage_due_at,
            'created_by' => $user?->id,
        ]);

        $this->maybeCreatePlaybookTask($lead, $stage, $log, $user);

        if (Schema::hasTable('lead_activities')) {
            $lead->activities()->create([
                'type' => 'status_change',
                'subject' => 'Этап бизнес-процесса',
                'content' => sprintf('Переход на этап «%s»', $stage->name),
                'created_by' => $user?->id,
            ]);
        }

        $this->activityLedger->record(
            $lead,
            ActivityEventType::ProcessStageChanged,
            'Этап бизнес-процесса',
            sprintf('Переход на этап «%s»', $stage->name),
            [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'business_process_id' => $stage->business_process_id,
            ],
            $now,
            $user,
            $log,
        );

        return $this->applyTerminalOutcome($lead, $stage, $user);
    }

    public function advanceToNextStage(Lead $lead, ?User $user = null): ?BusinessProcessStage
    {
        $current = $lead->businessProcessStage;

        abort_if($current === null, 422, 'У лида не задан текущий этап процесса.');

        $next = BusinessProcessStage::query()
            ->where('business_process_id', $lead->business_process_id)
            ->where('sequence', '>', $current->sequence)
            ->where('is_terminal', false)
            ->orderBy('sequence')
            ->first();

        if ($next === null) {
            return null;
        }

        $this->moveLeadToStage($lead, $next, $user);

        return $next->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function progressPayload(Lead $lead): ?array
    {
        if (! $this->tablesReady() || $lead->business_process_id === null) {
            return null;
        }

        $lead->loadMissing(['businessProcess.stages', 'businessProcessStage.salesScript.versions']);

        $process = $lead->businessProcess;
        $currentStageId = $lead->business_process_stage_id;

        if ($process === null) {
            return null;
        }

        $stages = $process->stages;
        $total = $stages->count();
        $currentStage = $lead->businessProcessStage;
        $currentIndex = $stages->search(fn (BusinessProcessStage $stage): bool => (int) $stage->id === (int) $currentStageId);
        $completedCount = $currentIndex === false ? 0 : $currentIndex;
        $isTerminalCurrent = $currentStage?->is_terminal ?? false;
        $progressPercent = $isTerminalCurrent
            ? 100
            : ($total > 0 ? (int) round(($completedCount / $total) * 100) : 0);

        $isOverdue = $this->isStageOverdue($lead);

        return [
            'process_id' => $process->id,
            'process_slug' => $process->slug,
            'process_name' => $process->name,
            'process_description' => $process->description,
            'current_stage_id' => $currentStageId,
            'current_stage_name' => $lead->businessProcessStage?->name,
            'current_stage_goal' => $currentStage?->stage_goal,
            'current_stage_playbook' => BusinessProcessPlaybook::normalize($currentStage?->description),
            'current_stage_success_criteria' => BusinessProcessPlaybook::normalize($currentStage?->success_criteria),
            'current_stage_sales_script' => $this->resolveStageSalesScriptPayload($currentStage),
            'stage_entered_at' => optional($lead->stage_entered_at)?->toIso8601String(),
            'stage_due_at' => optional($lead->stage_due_at)?->toIso8601String(),
            'process_started_at' => optional($lead->process_started_at)?->toIso8601String(),
            'progress_percent' => $progressPercent,
            'is_stage_overdue' => $isOverdue,
            'stages' => $stages->values()->map(function (BusinessProcessStage $stage, int $index) use ($currentIndex, $isTerminalCurrent): array {
                $state = 'upcoming';
                if ($currentIndex !== false) {
                    if ($isTerminalCurrent) {
                        $state = 'completed';
                    } elseif ($index < $currentIndex) {
                        $state = 'completed';
                    } elseif ($index === $currentIndex) {
                        $state = 'current';
                    }
                }

                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'sequence' => $stage->sequence,
                    'duration_days' => $stage->duration_days,
                    'is_terminal' => $stage->is_terminal,
                    'terminal_outcome' => $stage->terminal_outcome,
                    'stage_goal' => $stage->stage_goal,
                    'has_playbook' => filled($stage->description) || filled($stage->stage_goal),
                    'state' => $state,
                ];
            })->all(),
        ];
    }

    public function isStageOverdue(Lead $lead): bool
    {
        if (! $this->tablesReady() || $lead->business_process_id === null) {
            return false;
        }

        $lead->loadMissing('businessProcessStage');

        return $lead->stage_due_at !== null
            && $lead->stage_due_at->isPast()
            && ! $this->isTerminalStage($lead->businessProcessStage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function gridProcessFields(Lead $lead): ?array
    {
        if (! $this->tablesReady() || $lead->business_process_id === null) {
            return null;
        }

        $lead->loadMissing(['businessProcess:id,name', 'businessProcessStage:id,name,is_terminal']);

        return [
            'process_name' => $lead->businessProcess?->name,
            'current_stage_name' => $lead->businessProcessStage?->name,
            'stage_due_at' => optional($lead->stage_due_at)?->toIso8601String(),
            'is_stage_overdue' => $this->isStageOverdue($lead),
        ];
    }

    public function makeSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'process';
        }

        $slug = $base;
        $counter = 1;

        while (BusinessProcess::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }

    private function resolveStageDueAt(BusinessProcessStage $stage, Carbon $enteredAt): ?Carbon
    {
        if ((int) $stage->duration_days <= 0) {
            return null;
        }

        return $enteredAt->copy()->addDays((int) $stage->duration_days);
    }

    private function closeOpenLog(Lead $lead, Carbon $exitedAt): void
    {
        if (! Schema::hasTable('lead_process_stage_logs')) {
            return;
        }

        LeadProcessStageLog::query()
            ->where('lead_id', $lead->id)
            ->whereNull('exited_at')
            ->update(['exited_at' => $exitedAt]);
    }

    private function applyTerminalOutcome(Lead $lead, BusinessProcessStage $stage, ?User $user = null): int
    {
        if (! $stage->is_terminal || $stage->terminal_outcome === null) {
            return 0;
        }

        $status = match ($stage->terminal_outcome) {
            'won' => 'won',
            'lost' => 'lost',
            default => $lead->status,
        };

        if ($status !== $lead->status) {
            $lead->forceFill(['status' => $status])->saveQuietly();
        }

        if ($stage->terminal_outcome === 'lost') {
            return app(LeadLinkedTaskService::class)->cancelOpenTasksForLostLead($lead->fresh(), $user);
        }

        return 0;
    }

    private function isTerminalStage(?BusinessProcessStage $stage): bool
    {
        return $stage !== null && $stage->is_terminal;
    }

    private function maybeCreatePlaybookTask(Lead $lead, BusinessProcessStage $stage, LeadProcessStageLog $log, ?User $user): void
    {
        if ($stage->is_terminal || ! Schema::hasTable('tasks') || ! $this->stageHasPlaybook($stage) || ! $stage->auto_create_task) {
            return;
        }

        if (Task::query()
            ->where('lead_id', $lead->id)
            ->where('meta->playbook_log_id', $log->id)
            ->exists()) {
            return;
        }

        $lead->loadMissing(['businessProcess:id,name', 'responsible:id']);

        $title = $this->renderPlaybookTemplate(
            $stage->task_title_template ?: '{stage_name} — {lead_number}',
            $lead,
            $stage,
        );

        $description = filled($stage->task_description_template)
            ? BusinessProcessPlaybook::toPlainText(
                $this->renderPlaybookTemplate($stage->task_description_template, $lead, $stage),
            )
            : null;

        $dueAt = (int) ($stage->task_due_days_offset ?? 0) > 0
            ? now()->addDays((int) $stage->task_due_days_offset)
            : $lead->stage_due_at;

        Task::query()->create([
            'number' => $this->nextTaskNumber(),
            'title' => $title,
            'description' => $description,
            'status' => 'new',
            'priority' => $stage->task_priority ?: 'medium',
            'due_at' => $dueAt,
            'responsible_id' => $lead->responsible_id ?? $user?->id,
            'created_by' => $user?->id,
            'lead_id' => $lead->id,
            'meta' => [
                'playbook_log_id' => $log->id,
                'playbook_stage_id' => $stage->id,
                'playbook_process_id' => $stage->business_process_id,
            ],
        ]);
    }

    private function stageHasPlaybook(BusinessProcessStage $stage): bool
    {
        return Schema::hasColumn('business_process_stages', 'auto_create_task');
    }

    private function renderPlaybookTemplate(string $template, Lead $lead, BusinessProcessStage $stage): string
    {
        $lead->loadMissing('businessProcess:id,name');

        $replacements = [
            '{stage}' => $stage->name,
            '{stage_name}' => $stage->name,
            '{process}' => $lead->businessProcess?->name ?? '',
            '{process_name}' => $lead->businessProcess?->name ?? '',
            '{lead}' => $lead->title,
            '{lead_title}' => $lead->title,
            '{lead_number}' => $lead->number,
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    /**
     * @return array{id: int, title: string, version_id: int}|null
     */
    private function resolveStageSalesScriptPayload(?BusinessProcessStage $stage): ?array
    {
        if ($stage === null || $stage->sales_script_id === null || ! Schema::hasTable('sales_scripts')) {
            return null;
        }

        $stage->loadMissing(['salesScript.versions']);

        $script = $stage->salesScript;
        if ($script === null) {
            return null;
        }

        /** @var SalesScriptVersion|null $published */
        $published = $script->versions
            ->first(fn (SalesScriptVersion $version): bool => $version->is_active && $version->published_at !== null);

        if ($published === null) {
            return null;
        }

        return [
            'id' => (int) $script->id,
            'title' => (string) $script->title,
            'version_id' => (int) $published->id,
        ];
    }

    private function nextTaskNumber(): string
    {
        $prefix = 'TSK-'.now()->format('ymd');

        if (! Schema::hasTable('tasks')) {
            return sprintf('%s-%03d', $prefix, 1);
        }

        $sequence = DB::table('tasks')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
