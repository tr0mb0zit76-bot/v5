<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyInitiativeMilestoneRequest;
use App\Http\Requests\StoreCompanyInitiativeRequest;
use App\Http\Requests\UpdateCompanyInitiativeMilestoneRequest;
use App\Http\Requests\UpdateCompanyInitiativeRequest;
use App\Models\CompanyInitiative;
use App\Models\CompanyInitiativeMilestone;
use App\Models\ManagementExpenseCategory;
use App\Models\Task;
use App\Models\User;
use App\Services\CompanyPlanning\CompanyPlanningProgressService;
use App\Support\CompanyPlanningCatalog;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPlanningController extends Controller
{
    public function __construct(
        private readonly CompanyPlanningProgressService $progressService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(RoleAccess::canAccessCompanyPlanning($request->user()), 403);

        $statusFilter = $request->string('status')->toString();
        if ($statusFilter !== '' && ! in_array($statusFilter, CompanyPlanningCatalog::INITIATIVE_STATUSES, true)) {
            $statusFilter = '';
        }

        $initiatives = CompanyInitiative::query()
            ->with(['owner:id,name', 'managementExpenseCategory:id,name,code'])
            ->withCount('milestones')
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->orderByRaw("FIELD(status, 'active', 'on_hold', 'draft', 'completed', 'cancelled')")
            ->orderByDesc('id')
            ->get()
            ->map(fn (CompanyInitiative $initiative): array => $this->serializeInitiativeSummary($initiative));

        return Inertia::render('CompanyPlanning/Index', [
            'initiatives' => $initiatives,
            'status_filter' => $statusFilter,
            'status_labels' => CompanyPlanningCatalog::initiativeStatusLabels(),
            'priority_labels' => CompanyPlanningCatalog::priorityLabels(),
            'direction_labels' => CompanyPlanningCatalog::directionLabels(),
            'risk_labels' => CompanyPlanningCatalog::riskLevelLabels(),
            'users' => $this->managementUsers(),
            'expense_categories' => $this->expenseCategoryOptions(),
        ]);
    }

    public function show(Request $request, CompanyInitiative $initiative): Response
    {
        abort_unless(RoleAccess::canAccessCompanyPlanning($request->user()), 403);

        $initiative->load([
            'owner:id,name',
            'creator:id,name',
            'managementExpenseCategory:id,name,code',
            'milestones.responsible:id,name',
            'milestones.task:id,number,title,status',
        ]);

        return Inertia::render('CompanyPlanning/Show', [
            'initiative' => $this->serializeInitiativeDetail($initiative),
            'status_labels' => CompanyPlanningCatalog::initiativeStatusLabels(),
            'milestone_status_labels' => CompanyPlanningCatalog::milestoneStatusLabels(),
            'priority_labels' => CompanyPlanningCatalog::priorityLabels(),
            'direction_labels' => CompanyPlanningCatalog::directionLabels(),
            'risk_labels' => CompanyPlanningCatalog::riskLevelLabels(),
            'users' => $this->managementUsers(),
            'expense_categories' => $this->expenseCategoryOptions(),
            'can_spawn_tasks' => Schema::hasTable('tasks')
                && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($request->user()), 'tasks'),
        ]);
    }

    public function store(StoreCompanyInitiativeRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $initiative = CompanyInitiative::query()->create([
            ...$payload,
            'status' => $payload['status'] ?? 'draft',
            'priority' => $payload['priority'] ?? 'normal',
            'risk_level' => $payload['risk_level'] ?? 'normal',
            'budget_currency' => strtoupper((string) ($payload['budget_currency'] ?? 'RUB')),
            'created_by' => $request->user()?->id,
            'owner_id' => $payload['owner_id'] ?? $request->user()?->id,
        ]);

        return to_route('company-planning.show', $initiative)
            ->with('flash', ['type' => 'success', 'message' => 'Инициатива создана.']);
    }

    public function update(UpdateCompanyInitiativeRequest $request, CompanyInitiative $initiative): RedirectResponse
    {
        $payload = $request->validated();

        if (array_key_exists('budget_currency', $payload) && is_string($payload['budget_currency'])) {
            $payload['budget_currency'] = strtoupper($payload['budget_currency']);
        }

        $initiative->update($payload);

        return to_route('company-planning.show', $initiative)
            ->with('flash', ['type' => 'success', 'message' => 'Инициатива сохранена.']);
    }

    public function destroy(Request $request, CompanyInitiative $initiative): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessCompanyPlanning($request->user()), 403);

        $initiative->delete();

        return to_route('company-planning.index')
            ->with('flash', ['type' => 'success', 'message' => 'Инициатива удалена.']);
    }

    public function storeMilestone(
        StoreCompanyInitiativeMilestoneRequest $request,
        CompanyInitiative $initiative,
    ): RedirectResponse {
        $payload = $request->validated();
        $maxSort = (int) $initiative->milestones()->max('sort_order');

        $milestone = $initiative->milestones()->create([
            ...$payload,
            'status' => $payload['status'] ?? 'planned',
            'priority' => $payload['priority'] ?? 'normal',
            'sort_order' => $payload['sort_order'] ?? ($maxSort + 10),
        ]);

        $this->progressService->syncMilestoneCompletion($milestone->fresh());

        return to_route('company-planning.show', $initiative)
            ->with('flash', ['type' => 'success', 'message' => 'Этап добавлен.']);
    }

    public function updateMilestone(
        UpdateCompanyInitiativeMilestoneRequest $request,
        CompanyInitiativeMilestone $milestone,
    ): RedirectResponse {
        $payload = $request->validated();
        $milestone->update($payload);
        $this->progressService->syncMilestoneCompletion($milestone->fresh());

        return to_route('company-planning.show', $milestone->company_initiative_id)
            ->with('flash', ['type' => 'success', 'message' => 'Этап сохранён.']);
    }

    public function destroyMilestone(Request $request, CompanyInitiativeMilestone $milestone): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessCompanyPlanning($request->user()), 403);

        $initiativeId = (int) $milestone->company_initiative_id;
        $milestone->delete();
        $initiative = CompanyInitiative::query()->find($initiativeId);

        if ($initiative !== null) {
            $this->progressService->recalculateInitiative($initiative);
        }

        return to_route('company-planning.show', $initiativeId)
            ->with('flash', ['type' => 'success', 'message' => 'Этап удалён.']);
    }

    public function spawnTask(Request $request, CompanyInitiativeMilestone $milestone): RedirectResponse
    {
        abort_unless(RoleAccess::canAccessCompanyPlanning($request->user()), 403);
        abort_unless(Schema::hasTable('tasks'), 404);
        abort_unless(
            RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($request->user()), 'tasks'),
            403,
        );

        if ($milestone->task_id !== null) {
            return to_route('company-planning.show', $milestone->company_initiative_id)
                ->with('flash', ['type' => 'info', 'message' => 'Задача для этапа уже создана.']);
        }

        $initiative = $milestone->initiative;
        abort_if($initiative === null, 404);

        $task = Task::query()->create([
            'number' => $this->nextTaskNumber(),
            'title' => $milestone->title,
            'description' => $milestone->description,
            'status' => 'new',
            'priority' => $milestone->priority === 'critical' ? 'high' : $milestone->priority,
            'due_at' => $milestone->ends_on?->endOfDay(),
            'responsible_id' => $milestone->responsible_id ?? $initiative->owner_id ?? $request->user()?->id,
            'created_by' => $request->user()?->id,
            'company_initiative_id' => $initiative->id,
            'company_initiative_milestone_id' => $milestone->id,
        ]);

        $milestone->update(['task_id' => $task->id]);

        return to_route('company-planning.show', $initiative)
            ->with('flash', ['type' => 'success', 'message' => 'Задача для этапа создана.']);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function managementUsers(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, code: string|null}>
     */
    private function expenseCategoryOptions(): array
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return [];
        }

        return ManagementExpenseCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (ManagementExpenseCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'code' => $category->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInitiativeSummary(CompanyInitiative $initiative): array
    {
        return [
            'id' => (int) $initiative->id,
            'title' => $initiative->title,
            'status' => $initiative->status,
            'status_label' => CompanyPlanningCatalog::initiativeStatusLabels()[$initiative->status] ?? $initiative->status,
            'priority' => $initiative->priority,
            'direction' => $initiative->direction,
            'direction_label' => $initiative->direction
                ? (CompanyPlanningCatalog::directionLabels()[$initiative->direction] ?? $initiative->direction)
                : null,
            'starts_on' => optional($initiative->starts_on)?->toDateString(),
            'ends_on' => optional($initiative->ends_on)?->toDateString(),
            'owner_id' => $initiative->owner_id,
            'owner_name' => $initiative->owner?->name,
            'progress_percent' => (int) $initiative->progress_percent,
            'planned_budget_amount' => $initiative->planned_budget_amount,
            'budget_currency' => $initiative->budget_currency,
            'risk_level' => $initiative->risk_level,
            'milestones_count' => (int) ($initiative->milestones_count ?? 0),
            'show_url' => route('company-planning.show', $initiative),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInitiativeDetail(CompanyInitiative $initiative): array
    {
        return [
            ...$this->serializeInitiativeSummary($initiative),
            'description' => $initiative->description,
            'goal' => $initiative->goal,
            'expected_result' => $initiative->expected_result,
            'budget_notes' => $initiative->budget_notes,
            'risk_summary' => $initiative->risk_summary,
            'management_expense_category_id' => $initiative->management_expense_category_id,
            'expense_category_name' => $initiative->managementExpenseCategory?->name,
            'creator_name' => $initiative->creator?->name,
            'milestones' => $initiative->milestones
                ->map(fn (CompanyInitiativeMilestone $milestone): array => [
                    'id' => (int) $milestone->id,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'done_criteria' => $milestone->done_criteria,
                    'status' => $milestone->status,
                    'status_label' => CompanyPlanningCatalog::milestoneStatusLabels()[$milestone->status] ?? $milestone->status,
                    'priority' => $milestone->priority,
                    'responsible_id' => $milestone->responsible_id,
                    'responsible_name' => $milestone->responsible?->name,
                    'starts_on' => optional($milestone->starts_on)?->toDateString(),
                    'ends_on' => optional($milestone->ends_on)?->toDateString(),
                    'completed_on' => optional($milestone->completed_on)?->toDateString(),
                    'progress_percent' => (int) $milestone->progress_percent,
                    'sort_order' => (int) $milestone->sort_order,
                    'task_id' => $milestone->task_id,
                    'task_number' => $milestone->task?->number,
                    'task_title' => $milestone->task?->title,
                    'task_status' => $milestone->task?->status,
                ])
                ->values()
                ->all(),
        ];
    }

    private function nextTaskNumber(): string
    {
        $latest = Task::query()->orderByDesc('id')->value('number');
        if (! is_string($latest) || ! preg_match('/(\d+)$/', $latest, $matches)) {
            return 'TSK-'.now()->format('ymd').'-001';
        }

        $next = (int) $matches[1] + 1;

        return preg_replace('/\d+$/', str_pad((string) $next, strlen($matches[1]), '0', STR_PAD_LEFT), $latest) ?? ('TSK-'.$next);
    }
}
