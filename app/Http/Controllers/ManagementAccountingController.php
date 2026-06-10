<?php

namespace App\Http\Controllers;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Services\ManagementAccounting\ManagementAccountingAnalyticsService;
use App\Services\ManagementAccounting\ManagementBankAccountSyncService;
use App\Services\ManagementAccounting\ManagementPayrollHalfService;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagementAccountingController extends Controller
{
    public function __construct(
        private readonly ManagementAccountingAnalyticsService $analyticsService,
        private readonly ManagementBankAccountSyncService $bankAccountSyncService,
        private readonly ManagementPayrollHalfService $payrollHalfService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessManagementAccounting($request->user()), 403);

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
            ]);

        $this->bankAccountSyncService->syncFromOwnCompanies();

        $bankAccounts = ManagementBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'bank_name', 'account_mask', 'currency']);

        $tab = (string) $request->string('tab');
        if (! in_array($tab, ['payments', 'ledger'], true)) {
            $tab = 'payments';
        }

        $periodType = $this->analyticsService->normalizePeriodType((string) $request->string('period_type'));
        $periodAnchor = $request->input('period_anchor');
        $periodAnchor = is_string($periodAnchor) && $periodAnchor !== '' ? $periodAnchor : null;

        return Inertia::render('Finance/ManagementAccounting/Index', [
            'filters' => [
                'tab' => $tab,
                'period_type' => $periodType,
                'period_anchor' => $periodAnchor ?? now()->startOfMonth()->toDateString(),
            ],
            'bank_accounts' => $bankAccounts,
            'default_bank_account_id' => $bankAccounts->first()?->id,
            'categories' => ManagementExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'kind', 'is_system'])
                ->map(static fn (ManagementExpenseCategory $category): array => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'kind' => $category->kind,
                    'is_system' => $category->is_system,
                    'source' => self::categorySource($category),
                ]),
            'imports' => $imports,
            'payroll_halves' => $this->payrollHalfService->recentHalves(),
            'current_payroll_half' => $this->payrollHalfService->ensureCurrentHalf(),
            'analytics' => $this->analyticsService->build($periodType, $periodAnchor),
        ]);
    }

    private static function categorySource(ManagementExpenseCategory $category): string
    {
        if ($category->is_system) {
            return 'system';
        }

        if (str_starts_with($category->code, 'budget_opex_')) {
            return 'budget';
        }

        return 'custom';
    }
}
