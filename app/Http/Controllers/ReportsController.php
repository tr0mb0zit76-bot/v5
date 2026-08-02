<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ManagerTeamReportRequest;
use App\Services\Reports\FinancialReportsService;
use App\Services\Reports\LeadProcessReportsService;
use App\Services\Reports\ManagerTeamMetricCatalog;
use App\Services\Reports\ManagerTeamReportService;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(
        ManagerTeamReportRequest $request,
        FinancialReportsService $financialReports,
        LeadProcessReportsService $leadProcessReports,
        ManagerTeamReportService $managerTeamReport,
    ): Response {
        $validated = $request->validated();

        $user = $request->user();
        $orderScope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');
        $leadsScope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');
        $hasLeadsAccess = RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads');

        $dateFrom = Carbon::parse($validated['date_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfMonth();
        }

        $tab = $validated['tab'] ?? 'abc';
        if (in_array($tab, ['lead-sla', 'lead-stuck'], true)) {
            $tab = 'lead-process';
        }
        if (! in_array($tab, ['abc', 'xyz', 'managers', 'lead-process'], true)) {
            $tab = 'abc';
        }

        $party = ($validated['party'] ?? 'customer') === 'carrier' ? 'carrier' : 'customer';

        $stuckDays = (int) ($validated['stuck_days'] ?? LeadProcessReportsService::STUCK_STAGE_DAYS);
        $stuckDays = max(1, min(365, $stuckDays));

        $managersMode = $validated['managers_mode'] ?? ManagerTeamMetricCatalog::MODE_PERIOD;
        if (! in_array($managersMode, ManagerTeamMetricCatalog::modes(), true)) {
            $managersMode = ManagerTeamMetricCatalog::MODE_PERIOD;
        }

        $userIds = array_values(array_map('intval', $validated['user_ids'] ?? []));
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $metrics = array_values(array_map('strval', $validated['metrics'] ?? []));

        $leadProcess = ($tab === 'lead-process' && $hasLeadsAccess)
            ? $leadProcessReports->processStageIssues($user, $stuckDays)
            : ['rows' => [], 'stuck_days' => $stuckDays];

        $teamReport = $tab === 'managers'
            ? $managerTeamReport->build(
                $user,
                $managersMode,
                $dateFrom,
                $dateTo,
                $userIds,
                $departmentId,
                $metrics,
            )
            : null;

        return Inertia::render('Reports/Index', [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'party' => $party,
                'stuck_days' => $stuckDays,
                'managers_mode' => $managersMode,
                'user_ids' => $userIds,
                'department_id' => $departmentId,
                'metrics' => $metrics !== [] ? $metrics : ($teamReport['filters']['metrics'] ?? []),
            ],
            'tab' => $tab,
            'order_scope' => $orderScope,
            'leads_scope' => $leadsScope,
            'has_leads_access' => $hasLeadsAccess,
            'lead_process' => $leadProcess,
            'abc' => $tab === 'abc'
                ? $financialReports->abcByContractorParty($dateFrom, $dateTo, $user, $party)
                : ['rows' => [], 'total_revenue' => 0, 'total_orders' => 0, 'party' => $party],
            'xyz' => $tab === 'xyz'
                ? $financialReports->xyzByContractorParty($dateFrom, $dateTo, $user, 6, $party)
                : ['rows' => [], 'months' => [], 'party' => $party],
            'managers' => [],
            'team_report' => $teamReport,
            'glossary' => [
                'abc_customer' => 'ABC (клиенты): классификация заказчиков по доле накопленной выручки (ставка клиента в заказах) за период. A — до 80% накопленной суммы, B — до 95%, C — остальное. Перевозчики с типом «только перевозчик» не участвуют.',
                'abc_carrier' => 'ABC (перевозчики): классификация перевозчиков по доле накопленной суммы ставок перевозчика (carrier_rate) в заказах за период. A — до 80%, B — до 95%, C — остальное. Заказчики с типом «только заказчик» не участвуют.',
                'xyz_customer' => 'XYZ (клиенты): нестабильность помесячной выручки по заказчику за последние 6 месяцев относительно конца выбранного периода. CV = σ/μ. X (CV < 0,25) — ровный спрос, Y — умеренные колебания, Z — сильная нерегулярность.',
                'xyz_carrier' => 'XYZ (перевозчики): нестабильность помесячных сумм по перевозчику (carrier_rate) за последние 6 месяцев. CV = σ/μ. X — стабильный объём заказов, Y — умеренные колебания, Z — нерегулярность.',
                'managers' => $teamReport['glossary'] ?? ManagerTeamMetricCatalog::glossaryForMode($managersMode),
                'lead_process' => sprintf(
                    'Лиды с проблемой на этапе процесса: истёк календарный срок этапа (stage_due_at) и/или лид на нефинальном этапе дольше %d дн. без перехода. Период дат сверху не влияет.',
                    $stuckDays,
                ),
            ],
        ]);
    }

    public function managersDrillDown(
        Request $request,
        ManagerTeamReportService $managerTeamReport,
    ): JsonResponse {
        $validated = $request->validate([
            'managers_mode' => ['nullable', 'string', 'in:snapshot,period,compare'],
            'metric_key' => ['required', 'string', 'max:64'],
            'manager_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        $payload = $managerTeamReport->drillDown(
            $request->user(),
            $validated['managers_mode'] ?? ManagerTeamMetricCatalog::MODE_PERIOD,
            $validated['metric_key'],
            (int) $validated['manager_id'],
            $dateFrom,
            $dateTo,
            (int) ($validated['limit'] ?? 100),
        );

        return response()->json($payload);
    }
}
