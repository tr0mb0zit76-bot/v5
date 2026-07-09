<?php

namespace App\Http\Controllers;

use App\Http\Requests\FreezeBudgetPlanSnapshotRequest;
use App\Http\Requests\StoreBudgetOpexArticleRequest;
use App\Http\Requests\UpdateBudgetOpexArticleRequest;
use App\Http\Requests\UpdateBudgetSalesTargetsRequest;
use App\Http\Requests\UpdateBudgetScenarioRequest;
use App\Models\BudgetOpexArticle;
use App\Models\BudgetScenario;
use App\Services\Budgeting\BudgetMarginBenchmarkService;
use App\Services\Budgeting\BudgetPlannerService;
use App\Services\Budgeting\BudgetPlanSnapshotService;
use App\Services\Budgeting\BudgetSalesTargetService;
use App\Services\CompanyPlanning\CompanyPlanningBudgetLinkService;
use App\Support\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetingController extends Controller
{
    public function __construct(
        private readonly BudgetPlannerService $planner,
        private readonly BudgetMarginBenchmarkService $benchmarks,
        private readonly BudgetPlanSnapshotService $snapshotService,
        private readonly CompanyPlanningBudgetLinkService $companyPlanningBudgetLinks,
        private readonly BudgetSalesTargetService $salesTargetService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessBudgeting($request->user()), 403);

        $scenario = $this->resolveScenario();
        $opexArticles = $this->resolveOpexArticles($scenario);
        $inputs = $this->planner->normalizeInputs($scenario->inputs ?? []);
        $dbBenchmark = $this->benchmarks->lastMonthsSummary(6);
        $plan = $this->planner->buildPlan($inputs, $opexArticles, $dbBenchmark);

        return Inertia::render('Budgeting/Index', [
            'inputs' => $inputs,
            'plan' => $plan,
            'opex_articles' => $opexArticles,
            'management_accounting_categories_url' => route('finance.management-accounting.index', ['tab' => 'categories']),
            'db_benchmark' => $dbBenchmark,
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'updated_at' => optional($scenario->updated_at)?->toIso8601String(),
            ],
            'sales_plan' => $this->salesTargetService->buildPayload(
                $scenario,
                $request->query('sales_month'),
                (int) $inputs['horizon_months'],
            ),
            'plan_snapshots' => $this->snapshotService->recentSnapshots(),
            'can_freeze_plan' => RoleAccess::canAccessBudgeting($request->user()),
            'company_planning_initiatives' => RoleAccess::canAccessCompanyPlanning($request->user())
                ? $this->companyPlanningBudgetLinks->initiativesLinkedToCategories()
                : [],
            'company_planning_index_url' => RoleAccess::canAccessCompanyPlanning($request->user())
                ? route('company-planning.index')
                : null,
        ]);
    }

    public function updateSalesTargets(UpdateBudgetSalesTargetsRequest $request): RedirectResponse
    {
        $scenario = $this->resolveScenario();

        $this->salesTargetService->upsert(
            $scenario,
            CarbonImmutable::parse($request->validated('period_month'))->startOfMonth(),
            $request->validated('targets'),
        );

        return to_route('budgeting.index', [
            'sales_month' => CarbonImmutable::parse($request->validated('period_month'))->format('Y-m-01'),
        ])->with('flash', ['type' => 'success', 'message' => 'План продавцов сохранён.']);
    }

    public function freezePlan(FreezeBudgetPlanSnapshotRequest $request): RedirectResponse
    {
        $scenario = $this->resolveScenario();

        $this->snapshotService->freeze(
            $scenario,
            CarbonImmutable::parse($request->validated('period_start'))->startOfDay(),
            CarbonImmutable::parse($request->validated('period_end'))->startOfDay(),
            $request->validated('period_label'),
            $request->user(),
            $request->validated('notes'),
        );

        return to_route('budgeting.index')
            ->with('flash', ['type' => 'success', 'message' => 'План зафиксирован. Сравнение план/факт будет использовать этот снимок.']);
    }

    public function updateScenario(UpdateBudgetScenarioRequest $request): RedirectResponse
    {
        $scenario = $this->resolveScenario();
        $inputs = $this->planner->normalizeInputs($request->validated('inputs'));

        $scenario->update([
            'inputs' => $inputs,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return to_route('budgeting.index');
    }

    public function storeOpexArticle(StoreBudgetOpexArticleRequest $request): RedirectResponse
    {
        $maxOrder = (int) BudgetOpexArticle::query()->max('sort_order');

        BudgetOpexArticle::query()->create([
            ...$request->validated(),
            'sort_order' => $maxOrder + 10,
        ]);

        return to_route('budgeting.index');
    }

    public function updateOpexArticle(UpdateBudgetOpexArticleRequest $request, BudgetOpexArticle $opexArticle): RedirectResponse
    {
        $opexArticle->update(collect($request->validated())->only([
            'cost_type',
            'amount_monthly',
            'percent_of_margin',
            'ramp_months',
        ])->all());

        return to_route('budgeting.index');
    }

    public function destroyOpexArticle(Request $request, BudgetOpexArticle $opexArticle): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessBudgeting($request->user()), 403);

        $opexArticle->delete();

        return to_route('budgeting.index');
    }

    private function resolveScenario(): BudgetScenario
    {
        $scenario = BudgetScenario::query()->orderBy('id')->first();

        if ($scenario !== null) {
            return $scenario;
        }

        return BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => BudgetPlannerService::defaultInputs(),
        ]);
    }

    /**
     * @return list<array{id: int, name: string, amount_monthly: float, ramp_months: ?int, sort_order: int}>
     */
    private function resolveOpexArticles(BudgetScenario $scenario): array
    {
        $articles = BudgetOpexArticle::query()
            ->with('managementExpenseCategory:id,name,code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($articles->isEmpty()) {
            $this->seedOpexArticlesFromLegacyInputs($scenario->inputs ?? []);
            $articles = BudgetOpexArticle::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return $articles->map(fn (BudgetOpexArticle $article): array => [
            'id' => $article->id,
            'name' => $article->managementExpenseCategory?->name ?? $article->name,
            'management_expense_category_id' => $article->management_expense_category_id,
            'category_code' => $article->managementExpenseCategory?->code,
            'cost_type' => $article->cost_type ?? BudgetOpexArticle::COST_FIXED_MONTHLY,
            'amount_monthly' => (float) $article->amount_monthly,
            'percent_of_margin' => $article->percent_of_margin !== null ? (float) $article->percent_of_margin : null,
            'ramp_months' => $article->ramp_months,
            'sort_order' => (int) $article->sort_order,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $legacyInputs
     */
    private function seedOpexArticlesFromLegacyInputs(array $legacyInputs): void
    {
        $defaults = [
            ['name' => 'Офис', 'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY, 'amount_monthly' => (float) ($legacyInputs['office_monthly'] ?? 100_000), 'percent_of_margin' => null, 'ramp_months' => null, 'sort_order' => 10],
            ['name' => 'Бухгалтерия', 'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY, 'amount_monthly' => (float) ($legacyInputs['accounting_monthly'] ?? 200_000), 'percent_of_margin' => null, 'ramp_months' => null, 'sort_order' => 20],
            ['name' => 'Оклады менеджеров', 'cost_type' => BudgetOpexArticle::COST_FIXED_MONTHLY, 'amount_monthly' => (float) ($legacyInputs['manager_payroll_monthly'] ?? 75_000), 'percent_of_margin' => null, 'ramp_months' => (int) ($legacyInputs['manager_payroll_months'] ?? 3), 'sort_order' => 30],
        ];

        foreach ($defaults as $row) {
            BudgetOpexArticle::query()->create($row);
        }
    }
}
