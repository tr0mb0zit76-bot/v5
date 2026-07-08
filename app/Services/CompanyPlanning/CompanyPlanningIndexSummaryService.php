<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Models\CompanyInitiativeMilestone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class CompanyPlanningIndexSummaryService
{
    /**
     * @return array{
     *     active_count: int,
     *     overdue_initiatives_count: int,
     *     overdue_milestones_count: int,
     *     high_risk_count: int,
     *     upcoming_milestones_count: int
     * }
     */
    public function summarize(): array
    {
        if (! Schema::hasTable('company_initiatives')) {
            return [
                'active_count' => 0,
                'overdue_initiatives_count' => 0,
                'overdue_milestones_count' => 0,
                'high_risk_count' => 0,
                'upcoming_milestones_count' => 0,
            ];
        }

        $today = now()->toDateString();
        $upcomingUntil = now()->addDays(7)->toDateString();

        return [
            'active_count' => CompanyInitiative::query()
                ->whereIn('status', ['active', 'on_hold'])
                ->count(),
            'overdue_initiatives_count' => CompanyInitiative::query()
                ->whereIn('status', ['active', 'on_hold', 'draft'])
                ->whereNotNull('ends_on')
                ->whereDate('ends_on', '<', $today)
                ->count(),
            'overdue_milestones_count' => $this->overdueMilestonesQuery($today)->count(),
            'high_risk_count' => CompanyInitiative::query()
                ->whereIn('status', ['active', 'on_hold', 'draft'])
                ->whereIn('risk_level', ['high', 'critical'])
                ->count(),
            'upcoming_milestones_count' => CompanyInitiativeMilestone::query()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('ends_on')
                ->whereDate('ends_on', '>=', $today)
                ->whereDate('ends_on', '<=', $upcomingUntil)
                ->count(),
        ];
    }

    /**
     * @return Builder<CompanyInitiativeMilestone>
     */
    private function overdueMilestonesQuery(string $today)
    {
        return CompanyInitiativeMilestone::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('ends_on')
            ->whereDate('ends_on', '<', $today);
    }
}
