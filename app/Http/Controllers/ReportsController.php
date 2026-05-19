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
            'stuck_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $user = $request->user();
        $orderScope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');
        $managerId = $orderScope === 'own' ? $user->id : null;
        $leadsScope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');
        $leadsManagerId = $leadsScope === 'own' ? $user->id : null;
        $hasLeadsAccess = RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads');

        $dateFrom = Carbon::parse($validated['date_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfMonth();
        }

        $tab = $validated['tab'] ?? 'abc';
        if (! in_array($tab, ['abc', 'xyz', 'managers', 'lead-sla', 'lead-stuck'], true)) {
            $tab = 'abc';
        }

        $stuckDays = (int) ($validated['stuck_days'] ?? LeadProcessReportsService::STUCK_STAGE_DAYS);
        $stuckDays = max(1, min(365, $stuckDays));

        $leadSlaRows = $hasLeadsAccess ? $leadProcessReports->stageSlaBreached($leadsManagerId) : [];
        $leadStuckRows = $hasLeadsAccess ? $leadProcessReports->stuckOnStage($leadsManagerId, $stuckDays) : [];

        return Inertia::render('Reports/Index', [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'stuck_days' => $stuckDays,
            ],
            'tab' => $tab,
            'order_scope' => $orderScope,
            'leads_scope' => $leadsScope,
            'has_leads_access' => $hasLeadsAccess,
            'lead_sla' => ['rows' => $leadSlaRows],
            'lead_stuck' => [
                'rows' => $leadStuckRows,
                'stuck_days' => $stuckDays,
            ],
            'abc' => $financialReports->abcByCustomer($dateFrom, $dateTo, $managerId),
            'xyz' => $financialReports->xyzByCustomer($dateFrom, $dateTo, $managerId, 6),
            'managers' => $financialReports->managerStatsByCompletedOrders($dateFrom, $dateTo, $managerId),
            'glossary' => [
                'abc' => 'ABC: классификация клиентов по доле накопленной выручки (ставка клиента в заказах) за период. A — до 80% накопленной суммы, B — до 95%, C — остальное.',
                'xyz' => 'XYZ: нестабильность помесячной выручки по клиенту за последние 6 месяцев относительно конца выбранного периода. CV = σ/μ. X (CV < 0,25) — ровный спрос, Y — умеренные колебания, Z — сильная нерегулярность.',
                'managers' => 'Менеджеры: только заказы в статусе «Завершено» или legacy «completed». Дата в периоде — дата закрытия (или дата заказа, если дата закрытия не задана). Маржа — сумма поля «дельта», средний чек — средняя ставка клиента по заказам.',
                'lead_sla' => 'Просрочка этапа: текущий срез лидов с активным процессом, у которых срок этапа (stage_due_at) прошёл, а этап не финальный. Период сверху не влияет.',
                'lead_stuck' => sprintf(
                    'Застряли на этапе: лиды на нефинальном этапе дольше %d дн. без перехода (порог задаётся в фильтре). Период дат сверху не влияет.',
                    $stuckDays,
                ),
            ],
        ]);
    }
}
