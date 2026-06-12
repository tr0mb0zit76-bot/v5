<?php

namespace App\Http\Controllers;

use App\Models\ManagementExpenseCategory;
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
