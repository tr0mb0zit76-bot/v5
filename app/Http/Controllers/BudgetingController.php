<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBudgetScenarioRequest;
use App\Models\BudgetScenario;
use App\Services\Budgeting\BudgetTopDownPlannerService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetingController extends Controller
{
    public function __construct(
        private readonly BudgetTopDownPlannerService $planner,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessBudgeting($request->user()), 403);

        $scenario = $this->resolveScenario();
        $inputs = $this->planner->normalizeInputs($scenario->inputs ?? []);
        $plan = $this->planner->buildPlan($inputs);

        return Inertia::render('Budgeting/Index', [
            'inputs' => $inputs,
            'plan' => $plan,
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'updated_at' => optional($scenario->updated_at)?->toIso8601String(),
            ],
        ]);
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

    private function resolveScenario(): BudgetScenario
    {
        $scenario = BudgetScenario::query()->orderBy('id')->first();

        if ($scenario !== null) {
            return $scenario;
        }

        return BudgetScenario::query()->create([
            'name' => 'Основной',
            'inputs' => BudgetTopDownPlannerService::defaultInputs(),
        ]);
    }
}
