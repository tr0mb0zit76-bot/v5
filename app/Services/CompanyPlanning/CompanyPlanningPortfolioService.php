<?php

declare(strict_types=1);

namespace App\Services\CompanyPlanning;

use App\Models\CompanyInitiative;
use App\Support\CompanyPlanningCatalog;
use Illuminate\Support\Facades\Schema;

final class CompanyPlanningPortfolioService
{
    /**
     * @return array{
     *     available: bool,
     *     total_active: int,
     *     index_url: string|null,
     *     items: list<array{
     *         id: int,
     *         title: string,
     *         status: string,
     *         status_label: string,
     *         priority: string,
     *         risk_level: string,
     *         risk_label: string,
     *         progress_percent: int,
     *         owner_name: string|null,
     *         ends_on: string|null,
     *         overdue_milestones_count: int,
     *         is_overdue: bool,
     *         show_url: string
     *     }>
     * }
     */
    public function forDashboard(int $limit = 8): array
    {
        if (! Schema::hasTable('company_initiatives')) {
            return [
                'available' => false,
                'total_active' => 0,
                'index_url' => null,
                'items' => [],
            ];
        }

        $statusLabels = CompanyPlanningCatalog::initiativeStatusLabels();
        $riskLabels = CompanyPlanningCatalog::riskLevelLabels();
        $today = now()->toDateString();

        $items = CompanyInitiative::query()
            ->with(['owner:id,name'])
            ->withCount([
                'milestones as overdue_milestones_count' => fn ($query) => $query
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereNotNull('ends_on')
                    ->whereDate('ends_on', '<', $today),
            ])
            ->whereIn('status', ['active', 'on_hold'])
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'normal', 'low')")
            ->orderByRaw("FIELD(status, 'active', 'on_hold')")
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (CompanyInitiative $initiative) use ($statusLabels, $riskLabels, $today): array {
                $endsOn = optional($initiative->ends_on)?->toDateString();

                return [
                    'id' => (int) $initiative->id,
                    'title' => (string) $initiative->title,
                    'status' => (string) $initiative->status,
                    'status_label' => $statusLabels[$initiative->status] ?? $initiative->status,
                    'priority' => (string) $initiative->priority,
                    'risk_level' => (string) $initiative->risk_level,
                    'risk_label' => $riskLabels[$initiative->risk_level] ?? $initiative->risk_level,
                    'progress_percent' => (int) $initiative->progress_percent,
                    'owner_name' => $initiative->owner?->name,
                    'ends_on' => $endsOn,
                    'overdue_milestones_count' => (int) ($initiative->overdue_milestones_count ?? 0),
                    'is_overdue' => $endsOn !== null
                        && $endsOn < $today
                        && ! in_array($initiative->status, ['completed', 'cancelled'], true),
                    'show_url' => route('company-planning.show', $initiative),
                ];
            })
            ->values()
            ->all();

        return [
            'available' => true,
            'total_active' => CompanyInitiative::query()
                ->whereIn('status', ['active', 'on_hold'])
                ->count(),
            'index_url' => route('company-planning.index'),
            'items' => $items,
        ];
    }
}
