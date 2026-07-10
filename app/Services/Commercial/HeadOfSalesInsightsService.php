<?php

declare(strict_types=1);

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\User;
use App\Services\Pipeline\PipelineKpiService;
use App\Services\Reports\FinancialReportsService;
use App\Services\Reports\LeadProcessReportsService;
use App\Services\SalesScripts\SalesScriptCoachingInsightsService;
use App\Support\RoleAccess;
use App\Support\UserDashboardDepartmentScope;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сводка для руководителя отдела продаж экспедиторской компании:
 * кто как работает, где узкие места воронки, что подкрутить.
 */
final class HeadOfSalesInsightsService
{
    public function __construct(
        private readonly FinancialReportsService $financialReports,
        private readonly ManagerSalesCoachingInsightsService $funnelCoaching,
        private readonly SalesScriptCoachingInsightsService $scriptCoaching,
        private readonly LeadProcessReportsService $leadProcessReports,
        private readonly PipelineKpiService $pipelineKpi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function insights(User $user, int $days = 90, ?int $filterUserId = null): array
    {
        if (! RoleAccess::canViewHeadOfSalesInsights($user)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к аналитике руководителя продаж.',
            ];
        }

        $days = max(7, min(365, $days));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);
        $from = Carbon::parse($since->toDateString());
        $to = Carbon::now()->endOfDay();
        $scope = $this->resolveScope($user);
        $scopedUserIds = $this->scopedUserIds($user, $scope);

        if ($filterUserId !== null && $filterUserId > 0 && ! $this->canFilterByUser($user, $filterUserId, $scopedUserIds)) {
            return [
                'available' => false,
                'message' => 'Нет доступа к данным выбранного менеджера.',
            ];
        }

        $managerFilterId = $filterUserId;
        $managerStats = $this->managerPerformance($from, $to, $user, $managerFilterId, $scopedUserIds);
        $funnel = $this->funnelCoaching->insights($user, $days, $managerFilterId, 8);
        $scripts = $this->scriptCoaching->insights($user, min($days, 60), $managerFilterId, 10);
        $pipeline = $this->pipelineKpi->metricsForUser($user, max(3, (int) ceil($days / 30)));
        $openFunnel = $this->openFunnelRisks($user, $managerFilterId, $scopedUserIds);
        $transportMix = $this->transportMix($since, $managerFilterId, $scopedUserIds);
        $perManagerFunnel = $this->perManagerFunnelSnapshots($user, $days, $managerStats, $scopedUserIds);

        $actions = $this->prioritizedActions(
            $managerStats,
            is_array($funnel) && ($funnel['available'] ?? false) ? $funnel : [],
            is_array($scripts) && ($scripts['available'] ?? false) ? $scripts : [],
            $openFunnel,
            $pipeline,
        );

        return [
            'available' => true,
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'scope' => $scope,
            'filter_user_id' => $managerFilterId,
            'executive_summary' => $this->executiveSummary($managerStats, $funnel, $openFunnel, $pipeline),
            'manager_performance' => $managerStats,
            'per_manager_funnel' => $perManagerFunnel,
            'funnel_coaching' => $this->compactSection($funnel, ['summary', 'loss_flag_counts', 'lost_hygiene_gap_counts', 'recommendations']),
            'script_coaching' => $this->compactSection($scripts, ['summary', 'weak_performers', 'top_objections', 'hotspots_by_script', 'recommendations']),
            'pipeline_kpi' => $pipeline,
            'open_funnel_risks' => $openFunnel,
            'transport_mix' => $transportMix,
            'prioritized_actions' => $actions,
        ];
    }

    /**
     * @return list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>
     */
    private function managerPerformance(Carbon $from, Carbon $to, User $viewer, ?int $filterUserId, array $scopedUserIds): array
    {
        $rows = $this->financialReports->managerStatsByCompletedOrders($from, $to, $viewer);

        if ($scopedUserIds === []) {
            return $rows;
        }

        $allowed = array_flip($scopedUserIds);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => isset($allowed[(int) $row['manager_id']]),
        ));
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>  $managerStats
     * @return list<array<string, mixed>>
     */
    private function perManagerFunnelSnapshots(User $user, int $days, array $managerStats, array $scopedUserIds): array
    {
        if (! RoleAccess::canViewSalesCoachingInsights($user)) {
            return [];
        }

        $candidates = collect($managerStats)
            ->sortByDesc('orders_count')
            ->take(5)
            ->values();

        $snapshots = [];

        foreach ($candidates as $row) {
            $managerId = (int) $row['manager_id'];

            if ($scopedUserIds !== [] && ! in_array($managerId, $scopedUserIds, true)) {
                continue;
            }

            $insight = $this->funnelCoaching->insights($user, $days, $managerId, 3);

            if (! ($insight['available'] ?? false)) {
                continue;
            }

            $snapshots[] = [
                'manager_id' => $managerId,
                'manager_name' => $row['manager_name'],
                'closed_leads' => $insight['summary']['closed_leads'] ?? 0,
                'win_rate_pct' => $insight['summary']['win_rate_pct'] ?? 0.0,
                'top_loss_flags' => array_slice($insight['loss_flag_counts'] ?? [], 0, 3, true),
                'recommendations' => array_slice($insight['recommendations'] ?? [], 0, 2),
            ];
        }

        return $snapshots;
    }

    /**
     * @param  list<int>  $scopedUserIds
     * @return array{total_issues: int, due_overdue: int, stuck_on_stage: int, top_rows: list<array<string, mixed>>}
     */
    private function openFunnelRisks(User $viewer, ?int $filterUserId, array $scopedUserIds): array
    {
        if ($filterUserId !== null) {
            $report = $this->leadProcessReports->processStageIssues($viewer, LeadProcessReportsService::STUCK_STAGE_DAYS, $filterUserId);

            return $this->serializeOpenFunnelReport($report);
        }

        if ($scopedUserIds === []) {
            $report = $this->leadProcessReports->processStageIssues($viewer);

            return $this->serializeOpenFunnelReport($report);
        }

        $merged = [];

        foreach ($scopedUserIds as $userId) {
            $report = $this->leadProcessReports->processStageIssues($viewer, LeadProcessReportsService::STUCK_STAGE_DAYS, $userId);
            foreach ($report['rows'] ?? [] as $row) {
                $merged[(int) $row['lead_id']] = $row;
            }
        }

        return $this->serializeOpenFunnelReport(['rows' => array_values($merged), 'stuck_days' => LeadProcessReportsService::STUCK_STAGE_DAYS]);
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, stuck_days?: int}  $report
     * @return array{total_issues: int, due_overdue: int, stuck_on_stage: int, top_rows: list<array<string, mixed>>}
     */
    private function serializeOpenFunnelReport(array $report): array
    {
        $rows = $report['rows'] ?? [];
        $dueOverdue = 0;
        $stuck = 0;

        foreach ($rows as $row) {
            $flags = $row['issue_flags'] ?? [];
            if (in_array('due_overdue', $flags, true)) {
                $dueOverdue++;
            }
            if (in_array('stuck', $flags, true)) {
                $stuck++;
            }
        }

        $topRows = collect($rows)
            ->take(12)
            ->map(fn (array $row): array => [
                'lead_id' => $row['lead_id'] ?? null,
                'lead_number' => $row['lead_number'] ?? null,
                'title' => $row['title'] ?? null,
                'responsible_name' => $row['responsible_name'] ?? null,
                'stage_name' => $row['stage_name'] ?? null,
                'issue_labels' => $row['issue_labels'] ?? [],
                'days_on_stage' => $row['days_on_stage'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'total_issues' => count($rows),
            'due_overdue' => $dueOverdue,
            'stuck_on_stage' => $stuck,
            'top_rows' => $topRows,
        ];
    }

    /**
     * @param  list<int>  $scopedUserIds
     * @return array{leads: list<array{transport_type: string, count: int}>, label_map: array<string, string>}
     */
    private function transportMix(CarbonImmutable $since, ?int $filterUserId, array $scopedUserIds): array
    {
        if (! Schema::hasTable('leads') || ! Schema::hasColumn('leads', 'transport_type')) {
            return ['leads' => [], 'label_map' => $this->transportTypeLabels()];
        }

        $query = Lead::query()
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', ['won', 'lost']);

        if ($filterUserId !== null) {
            $query->where('responsible_id', $filterUserId);
        } elseif ($scopedUserIds !== []) {
            $query->whereIn('responsible_id', $scopedUserIds);
        }

        $rows = $query
            ->select('transport_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('transport_type')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($row): array => [
                'transport_type' => (string) ($row->transport_type ?: 'unknown'),
                'count' => (int) $row->cnt,
            ])
            ->all();

        return [
            'leads' => $rows,
            'label_map' => $this->transportTypeLabels(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function transportTypeLabels(): array
    {
        return [
            'ftl' => 'FTL (полная загрузка)',
            'ltl' => 'LTL (сборный груз)',
            'container' => 'Контейнер',
            'multimodal' => 'Мультимодальный',
            'air' => 'Авиа',
            'rail' => 'Ж/Д',
            'unknown' => 'Не указан',
        ];
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>  $managerStats
     * @param  array<string, mixed>  $funnel
     * @param  array<string, mixed>  $scripts
     * @param  array{total_issues: int, due_overdue: int, stuck_on_stage: int}  $openFunnel
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    private function executiveSummary(array $managerStats, array $funnel, array $openFunnel, array $pipeline): array
    {
        $totalOrders = array_sum(array_column($managerStats, 'orders_count'));
        $totalMargin = array_sum(array_column($managerStats, 'margin'));
        $managersActive = count(array_filter($managerStats, fn (array $r): bool => ($r['orders_count'] ?? 0) > 0));

        return [
            'managers_with_closed_orders' => $managersActive,
            'closed_orders' => $totalOrders,
            'total_margin_rub' => round($totalMargin, 2),
            'team_win_rate_pct' => ($funnel['summary']['win_rate_pct'] ?? null),
            'open_lead_issues' => $openFunnel['total_issues'] ?? 0,
            'overdue_payments_percent' => $pipeline['overdue_payments_percent'] ?? null,
            'avg_lead_to_order_days' => $pipeline['avg_lead_to_order_days'] ?? null,
        ];
    }

    /**
     * @param  list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>  $managerStats
     * @param  array<string, mixed>  $funnel
     * @param  array<string, mixed>  $scripts
     * @param  array{total_issues: int, due_overdue: int, stuck_on_stage: int}  $openFunnel
     * @param  array<string, mixed>  $pipeline
     * @return list<string>
     */
    private function prioritizedActions(array $managerStats, array $funnel, array $scripts, array $openFunnel, array $pipeline): array
    {
        $actions = [];

        if (($openFunnel['total_issues'] ?? 0) >= 5) {
            $actions[] = sprintf(
                'В открытой воронке %d проблемных лидов (%d просрочен SLA, %d застряли на этапе) — разберите с ответственными на планёрке.',
                $openFunnel['total_issues'],
                $openFunnel['due_overdue'] ?? 0,
                $openFunnel['stuck_on_stage'] ?? 0,
            );
        }

        foreach ($funnel['recommendations'] ?? [] as $rec) {
            if (is_string($rec) && $rec !== '') {
                $actions[] = $rec;
            }
        }

        $weakScripts = $scripts['weak_performers'] ?? [];
        if ($weakScripts !== []) {
            $top = $weakScripts[0];
            $actions[] = sprintf(
                'По живым скриптам слабый результат у %s (проигрыш %.1f%%) — разбор сессий и обновление веток.',
                $top['user_name'] ?? 'менеджера',
                (float) ($top['lost_rate_pct'] ?? 0),
            );
        }

        if ($managerStats !== []) {
            $sorted = $managerStats;
            usort($sorted, fn (array $a, array $b): int => ($b['margin'] ?? 0) <=> ($a['margin'] ?? 0));
            $best = $sorted[0];
            $worst = end($sorted);

            if ($worst !== false && count($sorted) >= 2 && ($best['manager_id'] ?? 0) !== ($worst['manager_id'] ?? 0)) {
                $bestMargin = (float) ($best['margin'] ?? 0);
                $worstMargin = (float) ($worst['margin'] ?? 0);

                if ($bestMargin > 0 && $worstMargin < $bestMargin * 0.4) {
                    $actions[] = sprintf(
                        'Разрыв по марже: %s (%.0f ₽) vs %s (%.0f ₽) — выровняйте практики квалификации и расчёта КП.',
                        $best['manager_name'] ?? 'лидер',
                        $bestMargin,
                        $worst['manager_name'] ?? 'отстающий',
                        $worstMargin,
                    );
                }
            }
        }

        if (($pipeline['overdue_payments_percent'] ?? 0) >= 15) {
            $actions[] = sprintf(
                'На активном конвейере %.1f%% заказов с просроченной оплатой — синхронизируйте с бухгалтерией и менеджерами по закрытию.',
                (float) $pipeline['overdue_payments_percent'],
            );
        }

        if ($actions === []) {
            $actions[] = 'Явных системных отклонений не видно — продолжайте фиксировать причины закрытия лидов и вести next step в карточках.';
        }

        return array_values(array_unique(array_slice($actions, 0, 8)));
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function compactSection(array $section, array $keys): array
    {
        if (! ($section['available'] ?? false)) {
            return [
                'available' => false,
                'message' => $section['message'] ?? 'Недоступно',
            ];
        }

        $compact = ['available' => true];

        foreach ($keys as $key) {
            if (array_key_exists($key, $section)) {
                $compact[$key] = $section[$key];
            }
        }

        return $compact;
    }

    private function resolveScope(User $user): string
    {
        if ($user->isAdmin()) {
            return 'company';
        }

        if ($user->hasRole('supervisor')) {
            return 'department';
        }

        return 'self';
    }

    /**
     * @return list<int>
     */
    private function scopedUserIds(User $user, string $scope): array
    {
        if ($scope === 'company') {
            return [];
        }

        if ($scope === 'department') {
            return UserDashboardDepartmentScope::departmentUserIds($user);
        }

        return [(int) $user->id];
    }

    private function canFilterByUser(User $user, int $filterUserId, array $scopedUserIds): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($scopedUserIds === []) {
            return true;
        }

        return in_array($filterUserId, $scopedUserIds, true);
    }
}
