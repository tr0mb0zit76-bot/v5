<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiativeMilestone;
use App\Models\Task;

final class CompanyPlanningTaskSyncService
{
    public function __construct(
        private readonly CompanyPlanningProgressService $progressService,
    ) {}

    public function syncFromTask(Task $task): void
    {
        if ($task->company_initiative_milestone_id === null) {
            return;
        }

        $milestone = CompanyInitiativeMilestone::query()->find($task->company_initiative_milestone_id);
        if ($milestone === null) {
            return;
        }

        if ($task->status === 'done') {
            $milestone->forceFill([
                'status' => 'completed',
                'progress_percent' => 100,
                'completed_on' => $milestone->completed_on ?? now()->toDateString(),
            ])->saveQuietly();

            $this->progressService->recalculateInitiative($milestone->initiative);

            return;
        }

        if ($task->status === 'cancelled' && $milestone->status !== 'completed') {
            $milestone->forceFill([
                'status' => 'cancelled',
            ])->saveQuietly();

            $this->progressService->recalculateInitiative($milestone->initiative);
        }
    }

    public function syncFromTaskIfTerminal(Task $task): void
    {
        if (! in_array($task->status, ['done', 'cancelled'], true)) {
            return;
        }

        $this->syncFromTask($task);
    }
}
