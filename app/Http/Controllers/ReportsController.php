<?php

namespace App\Http\Controllers;

use App\Services\Reports\FinancialReportsService;
use App\Services\Reports\LeadProcessReportsService;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(
        Request $request,
        FinancialReportsService $financialReports,
        LeadProcessReportsService $leadProcessReports,
    ): Response {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tab' => ['nullable', 'string', 'max:32'],
            'party' => ['nullable', 'string', 'in:customer,carrier'],
            'stuck_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

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

        $leadProcess = $hasLeadsAccess
            ? $leadProcessReports->processStageIssues($user, $stuckDays)
            : ['rows' => [], 'stuck_days' => $stuckDays];

        return Inertia::render('Reports/Index', [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'party' => $party,
                'stuck_days' => $stuckDays,
            ],
            'tab' => $tab,
            'order_scope' => $orderScope,
            'leads_scope' => $leadsScope,
            'has_leads_access' => $hasLeadsAccess,
            'lead_process' => $leadProcess,
            'abc' => $financialReports->abcByContractorParty($dateFrom, $dateTo, $user, $party),
            'xyz' => $financialReports->xyzByContractorParty($dateFrom, $dateTo, $user, 6, $party),
            'managers' => $financialReports->managerStatsByCompletedOrders($dateFrom, $dateTo, $user),
            'glossary' => [
                'abc_customer' => 'ABC (клиенты): классификация заказчиков по доле накопленной выручки (ставка клиента в заказах) за период. A — до 80% накопленной суммы, B — до 95%, C — остальное. Перевозчики с типом «только перевозчик» не участвуют.',
                'abc_carrier' => 'ABC (перевозчики): классификация перевозчиков по доле накопленной суммы ставок перевозчика (carrier_rate) в заказах за период. A — до 80%, B — до 95%, C — остальное. Заказчики с типом «только заказчик» не участвуют.',
                'xyz_customer' => 'XYZ (клиенты): нестабильность помесячной выручки по заказчику за последние 6 месяцев относительно конца выбранного периода. CV = σ/μ. X (CV < 0,25) — ровный спрос, Y — умеренные колебания, Z — сильная нерегулярность.',
                'xyz_carrier' => 'XYZ (перевозчики): нестабильность помесячных сумм по перевозчику (carrier_rate) за последние 6 месяцев. CV = σ/μ. X — стабильный объём заказов, Y — умеренные колебания, Z — нерегулярность.',
                'managers' => 'Менеджеры: только заказы в статусе «Завершено» или legacy «completed». Дата в периоде — дата закрытия (или дата заказа, если дата закрытия не задана). Маржа — сумма поля «дельта», средний чек — средняя ставка клиента по заказам.',
                'lead_process' => sprintf(
                    'Лиды с проблемой на этапе процесса: истёк календарный срок этапа (stage_due_at) и/или лид на нефинальном этапе дольше %d дн. без перехода. Период дат сверху не влияет.',
                    $stuckDays,
                ),
            ],
        ]);
    }
}
