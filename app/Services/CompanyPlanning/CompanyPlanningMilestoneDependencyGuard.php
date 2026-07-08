<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiativeMilestone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CompanyPlanningMilestoneDependencyGuard
{
    /**
     * @return Collection<int, CompanyInitiativeMilestone>
     */
    public function incompletePredecessors(CompanyInitiativeMilestone $milestone): Collection
    {
        $initiative = $milestone->initiative()
            ->with([
                'dependencies.dependsOnMilestone:id,title,status',
            ])
            ->first();

        if ($initiative === null) {
            return collect();
        }

        return $initiative->dependencies
            ->where('blocked_milestone_id', $milestone->id)
            ->map(fn ($dependency) => $dependency->dependsOnMilestone)
            ->filter(fn (?CompanyInitiativeMilestone $predecessor): bool => $predecessor !== null
                && ! in_array($predecessor->status, ['completed', 'cancelled'], true))
            ->values();
    }

    /**
     * @param  list<string>  $targetStatuses
     */
    public function assertCanAdvance(CompanyInitiativeMilestone $milestone, array $targetStatuses): void
    {
        $blockingStatuses = ['in_progress', 'completed'];
        $shouldCheck = collect($targetStatuses)
            ->contains(fn (string $status): bool => in_array($status, $blockingStatuses, true));

        if (! $shouldCheck) {
            return;
        }

        $blockedBy = $this->incompletePredecessors($milestone);
        if ($blockedBy->isEmpty()) {
            return;
        }

        $titles = $blockedBy
            ->map(fn (CompanyInitiativeMilestone $item): string => (string) $item->title)
            ->implode(', ');

        throw ValidationException::withMessages([
            'status' => 'Сначала завершите предшествующие этапы: '.$titles.'.',
        ]);
    }
}
