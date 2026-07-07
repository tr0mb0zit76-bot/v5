<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Models\CompanyInitiativeMilestone;

final class CompanyPlanningProgressService
{
    public function recalculateInitiative(CompanyInitiative $initiative): void
    {
        $milestones = $initiative->milestones()
            ->whereNull('deleted_at')
            ->get(['progress_percent']);

        if ($milestones->isEmpty()) {
            return;
        }

        $average = (int) round((float) $milestones->avg('progress_percent'));

        $initiative->forceFill([
            'progress_percent' => max(0, min(100, $average)),
        ])->saveQuietly();
    }

    public function syncMilestoneCompletion(CompanyInitiativeMilestone $milestone): void
    {
        if ($milestone->status === 'completed' && $milestone->progress_percent < 100) {
            $milestone->forceFill(['progress_percent' => 100])->saveQuietly();
        }

        if ($milestone->status !== 'completed' && $milestone->progress_percent === 100) {
            $milestone->forceFill([
                'status' => 'completed',
                'completed_on' => $milestone->completed_on ?? now()->toDateString(),
            ])->saveQuietly();
        }

        $initiative = $milestone->initiative;
        if ($initiative !== null) {
            $this->recalculateInitiative($initiative);
        }
    }
}
