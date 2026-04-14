<?php

namespace App\Http\Controllers;

use App\Services\Finance\FinanceOverviewService;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceIndexController extends Controller
{
    public function __invoke(Request $request, FinanceOverviewService $financeOverview): Response|RedirectResponse
    {
        if ($request->query('section') === 'documents') {
            return redirect()->route('finance.index');
        }

        $user = $request->user();
        $role = $financeOverview->resolveRole($user?->role_id);
        $ordersScope = RoleAccess::resolveVisibilityScope($role['name'], $role['visibility_scopes'], 'orders');

        $activeSubmodule = match ($request->query('section')) {
            'dds', 'cashflow' => 'cashflow',
            default => 'overview',
        };

        PaymentScheduleAutomaticStatus::refreshForOrdersScope($user?->id, $role['name'], $ordersScope);

        $cashFlow = $financeOverview->cashFlowJournal($user?->id, $role['name'], $ordersScope);
        $cashFlowStats = $financeOverview->cashFlowStats($user?->id, $role['name'], $ordersScope);

        return Inertia::render('Finance/Index', [
            'summary' => [
                'cash_flow_total' => $cashFlow->count(),
                'cash_flow_pending' => $cashFlow->where('status', 'pending')->count(),
            ],
            'cashFlowJournal' => $cashFlow->values(),
            'active_submodule' => $activeSubmodule,
            'todays_cash_flow' => $cashFlowStats['periods']['today'],
            'cash_flow_stats' => $cashFlowStats,
            'can_access_salary_module' => RoleAccess::canAccessFinanceSalary($user),
        ]);
    }
}
