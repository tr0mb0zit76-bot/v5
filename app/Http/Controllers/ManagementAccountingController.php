<?php

namespace App\Http\Controllers;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Services\ManagementAccounting\ManagementAccountingAnalyticsService;
use App\Services\ManagementAccounting\ManagementExpenseCategoryTreeService;
use App\Support\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagementAccountingController extends Controller
{
    public function __construct(
        private readonly ManagementAccountingAnalyticsService $analyticsService,
        private readonly ManagementExpenseCategoryTreeService $categoryTreeService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessManagementAccounting($request->user()), 403);

        $tab = (string) $request->string('tab');
        if (! in_array($tab, ['ledger', 'categories'], true)) {
            $tab = 'ledger';
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
            'category_tree' => $this->categoryTreeService->treeForUi(),
            'categories' => ManagementExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'code', 'name', 'kind', 'flow', 'is_system', 'include_in_budget'])
                ->map(static fn (ManagementExpenseCategory $category): array => [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'kind' => $category->kind,
                    'flow' => $category->flow ?? 'out',
                    'is_system' => $category->is_system,
                    'include_in_budget' => (bool) $category->include_in_budget,
                    'source' => self::categorySource($category),
                ]),
            'bank_accounts' => ManagementBankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'bank_name', 'account_mask', 'currency'])
                ->map(static fn (ManagementBankAccount $account): array => [
                    'id' => $account->id,
                    'bank_name' => $account->bank_name,
                    'account_mask' => $account->account_mask,
                    'currency' => $account->currency,
                ]),
            'recent_manual_entries' => ManagementStatementLine::query()
                ->where('source', 'manual')
                ->with(['allocationCategory:id,name'])
                ->orderByDesc('operation_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'operation_date', 'direction', 'amount', 'description', 'status', 'allocation_category_id'])
                ->map(static fn (ManagementStatementLine $line): array => [
                    'id' => $line->id,
                    'operation_date' => $line->operation_date?->toDateString(),
                    'direction' => $line->direction,
                    'amount' => (float) $line->amount,
                    'description' => $line->description,
                    'status' => $line->status,
                    'category_name' => $line->allocationCategory?->name,
                ]),
            'can_manage_manual_entries' => RoleAccess::canAccessPaymentReconcile($request->user()),
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
