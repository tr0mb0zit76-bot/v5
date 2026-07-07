<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;

final class CompanyPlanningDependencyCycleGuard
{
    public function wouldCreateCycle(
        CompanyInitiative $initiative,
        int $blockedMilestoneId,
        int $dependsOnMilestoneId,
    ): bool {
        if ($blockedMilestoneId === $dependsOnMilestoneId) {
            return true;
        }

        $adjacency = [];

        foreach ($initiative->dependencies as $dependency) {
            $from = (int) $dependency->depends_on_milestone_id;
            $to = (int) $dependency->blocked_milestone_id;
            $adjacency[$from][] = $to;
        }

        return $this->canReach($adjacency, $blockedMilestoneId, $dependsOnMilestoneId);
    }

    /**
     * @param  array<int, list<int>>  $adjacency
     */
    private function canReach(array $adjacency, int $from, int $target): bool
    {
        $visited = [];
        $stack = [$from];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $target) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
