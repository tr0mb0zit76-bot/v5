<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Models\CompanyInitiativeMilestone;

final class CompanyPlanningMilestoneReorderService
{
    /**
     * @param  list<int>  $milestoneIds
     */
    public function reorder(CompanyInitiative $initiative, array $milestoneIds): void
    {
        foreach ($milestoneIds as $index => $milestoneId) {
            CompanyInitiativeMilestone::query()
                ->where('company_initiative_id', $initiative->id)
                ->whereKey($milestoneId)
                ->update(['sort_order' => ($index + 1) * 10]);
        }
    }
}
