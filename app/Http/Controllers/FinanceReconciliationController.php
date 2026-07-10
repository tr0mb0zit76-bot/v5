<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceReconciliationRequest;
use App\Services\Finance\ContractorReconciliationService;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceReconciliationController extends Controller
{
    public function index(Request $request, ContractorReconciliationService $reconciliationService): Response
    {
        abort_unless(RoleAccess::canViewPaymentSchedules($request->user()), 403);

        $filters = [
            'contractor_id' => $request->integer('contractor_id') ?: null,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $report = null;

        if ($filters['contractor_id']) {
            $user = $request->user();

            $report = $reconciliationService->build(
                (int) $filters['contractor_id'],
                $filters['date_from'],
                $filters['date_to'],
                $user,
            );
        }

        return Inertia::render('Finance/Reconciliation', [
            'contractorOptions' => $reconciliationService->contractorOptions(),
            'filters' => $filters,
            'report' => $report,
            'ledgerAvailable' => app(PaymentSchedulePaymentLedgerService::class)->ledgerTableExists(),
        ]);
    }

    public function store(FinanceReconciliationRequest $request): RedirectResponse
    {
        return redirect()->route('finance.reconciliation.index', $request->validated());
    }
}
