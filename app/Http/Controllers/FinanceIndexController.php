<?php

namespace App\Http\Controllers;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Services\Finance\FinanceOverviewService;
use App\Services\ManagementAccounting\ManagementBankAccountSyncService;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleTableColumns;
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

        $activeSubmodule = match ($request->query('section')) {
            'dds', 'cashflow' => 'cashflow',
            default => 'overview',
        };

        if ($activeSubmodule === 'cashflow' && ! RoleAccess::canViewPaymentSchedules($user)) {
            return redirect()->route('finance.index');
        }

        PaymentScheduleAutomaticStatus::refreshForUser($user);

        $cashFlow = $financeOverview->cashFlowJournal($user);
        $cashFlowStats = $financeOverview->cashFlowStats($user);

        $cashflowTab = (string) $request->query('cashflow_tab', 'schedule');
        if (! in_array($cashflowTab, ['schedule', 'reconcile'], true)) {
            $cashflowTab = 'schedule';
        }

        if ($cashflowTab === 'reconcile' && ! RoleAccess::canAccessPaymentReconcile($user)) {
            $cashflowTab = 'schedule';
        }

        $payload = [
            'summary' => [
                'cash_flow_total' => $cashFlow->count(),
                'cash_flow_pending' => $cashFlow->where('status', 'pending')->count(),
            ],
            'cashFlowJournal' => $cashFlow->values(),
            'active_submodule' => $activeSubmodule,
            'cashflow_tab' => $cashflowTab,
            'todays_cash_flow' => $cashFlowStats['periods']['today'],
            'cash_flow_stats' => $cashFlowStats,
            'can_access_salary_module' => RoleAccess::canAccessFinanceSalary($user),
            'can_access_management_accounting' => RoleAccess::canAccessManagementAccounting($user),
            'can_access_payment_reconcile' => RoleAccess::canAccessPaymentReconcile($user),
            'can_access_payment_schedules' => RoleAccess::canViewPaymentSchedules($user),
            'can_manage_payment_schedule' => RoleAccess::canManagePaymentSchedules($user),
            'can_show_payment_schedule_actions' => RoleAccess::canShowPaymentScheduleActionsColumn($user),
            'can_payment_schedule_record_payment' => RoleAccess::canRecordPaymentOnPaymentSchedule($user),
            'can_payment_schedule_cancel_row' => RoleAccess::canCancelPaymentScheduleRow($user),
            'paymentScheduleColumns' => PaymentScheduleTableColumns::options(),
        ];

        if ($activeSubmodule === 'cashflow' && $cashflowTab === 'reconcile') {
            $payload = [...$payload, ...$this->statementReconcileProps($request)];
        }

        return Inertia::render('Finance/Index', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function statementReconcileProps(Request $request): array
    {
        app(ManagementBankAccountSyncService::class)->syncFromOwnCompanies();

        $imports = ManagementStatementImport::query()
            ->with(['bankAccount:id,bank_name,account_mask,currency', 'importer:id,name'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(static fn (ManagementStatementImport $import): array => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'status' => $import->status,
                'period_from' => $import->period_from?->toDateString(),
                'period_to' => $import->period_to?->toDateString(),
                'lines_count' => $import->lines_count,
                'lines_allocated' => $import->lines_allocated,
                'total_in' => (float) $import->total_in,
                'total_out' => (float) $import->total_out,
                'bank_account' => $import->bankAccount === null ? null : [
                    'id' => $import->bankAccount->id,
                    'bank_name' => $import->bankAccount->bank_name,
                    'account_mask' => $import->bankAccount->account_mask,
                    'currency' => $import->bankAccount->currency,
                ],
                'importer_name' => $import->importer?->name,
                'created_at' => $import->created_at?->toIso8601String(),
                'pending_lines' => max(0, (int) $import->lines_count - (int) $import->lines_allocated),
                'has_allocated_lines' => (int) $import->lines_allocated > 0,
            ]);

        $bankAccounts = ManagementBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'bank_name', 'account_mask', 'currency']);

        return [
            'statement_imports' => $imports,
            'bank_accounts' => $bankAccounts,
            'default_bank_account_id' => $bankAccounts->first()?->id,
        ];
    }
}
