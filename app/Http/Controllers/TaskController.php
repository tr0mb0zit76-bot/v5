<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($this->canAccessTasks($request), 403);

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->taskRows($request),
            'statusOptions' => TaskStatus::options(),
            'quickFilters' => $this->quickFilters($request),
            'users' => $this->activeUsers(),
            'leadOptions' => $this->leadOptions($request),
        ]);
    }

    public function kanban(Request $request): Response
    {
        abort_unless($this->canAccessKanbanBoard($request), 403);

        return Inertia::render('Kanban/Index', [
            'tasks' => $this->taskRows($request),
            'statusOptions' => TaskStatus::options(),
            'featureUnavailable' => ! Schema::hasTable('tasks'),
            'canMutateTasks' => $this->canAccessTasks($request),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $task = Task::query()->create([
            'number' => $this->nextTaskNumber(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'new',
            'priority' => $validated['priority'],
            'due_at' => $validated['due_at'] ?? null,
            'responsible_id' => $validated['responsible_id'],
            'created_by' => $request->user()?->id,
            'lead_id' => $validated['lead_id'] ?? null,
            'order_id' => $validated['order_id'] ?? null,
            'contractor_id' => $validated['contractor_id'] ?? null,
        ]);

        $this->syncLinkedLeadStatus($task, $request->user()?->id);

        return to_route('tasks.index');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $validated = $request->validated();

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'due_at' => $validated['due_at'] ?? null,
            'responsible_id' => $validated['responsible_id'],
            'lead_id' => $validated['lead_id'] ?? null,
            'order_id' => $validated['order_id'] ?? null,
            'contractor_id' => $validated['contractor_id'] ?? null,
            'completed_at' => $validated['status'] === 'done' ? now() : null,
        ]);

        $this->syncLinkedLeadStatus($task, $request->user()?->id);

        return to_route('tasks.index');
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $status = $request->string('status')->toString();
        $task->update([
            'status' => $status,
            'completed_at' => $status === 'done' ? now() : null,
        ]);

        $this->syncLinkedLeadStatus($task, $request->user()?->id);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
        ]);
    }

    private function canAccessTasks(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks');
    }

    private function canAccessKanbanBoard(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $areas = RoleAccess::userVisibilityAreas($user);

        return RoleAccess::hasAnyVisibilityArea($areas, ['tasks', 'kanban']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskRows(Request $request): array
    {
        if (! Schema::hasTable('tasks')) {
            return [];
        }

        $user = $request->user();
        $scope = RoleAccess::resolveVisibilityScope($user?->role?->name, $user?->role?->visibility_scopes, 'tasks');

        return Task::query()
            ->with(['responsible:id,name', 'lead:id,number,title'])
            ->when(
                $user !== null && ! $user->isAdmin() && $scope !== 'all',
                fn ($query) => $query->where('responsible_id', $user->id)
            )
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'number' => $task->number,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'status_label' => TaskStatus::label($task->status),
                'priority' => $task->priority,
                'due_at' => optional($task->due_at)?->format('Y-m-d\TH:i'),
                'completed_at' => optional($task->completed_at)?->toIso8601String(),
                'responsible_id' => $task->responsible_id,
                'responsible_name' => $task->responsible?->name,
                'lead_id' => $task->lead_id,
                'lead_number' => $task->lead?->number,
                'lead_title' => $task->lead?->title,
                'order_id' => $task->order_id,
                'contractor_id' => $task->contractor_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string,count:int}>
     */
    private function quickFilters(Request $request): array
    {
        $tasks = collect($this->taskRows($request));

        return [
            ['label' => 'Все', 'count' => $tasks->count()],
            ['label' => 'Срочные', 'count' => $tasks->where('priority', 'critical')->count()],
            ['label' => 'В работе', 'count' => $tasks->where('status', 'in_progress')->count()],
            ['label' => 'На проверке', 'count' => $tasks->where('status', 'review')->count()],
            ['label' => 'Просроченные', 'count' => $tasks->filter(function (array $task): bool {
                if (($task['status'] ?? '') === 'done' || blank($task['due_at'] ?? null)) {
                    return false;
                }

                return Carbon::parse($task['due_at'])->isPast();
            })->count()],
        ];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function activeUsers(): array
    {
        return User::query()
            ->when(
                Schema::hasColumn('users', 'is_active'),
                fn ($query) => $query->where('is_active', true)
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,number:string,title:string}>
     */
    private function leadOptions(Request $request): array
    {
        if (! Schema::hasTable('leads')) {
            return [];
        }

        $user = $request->user();
        $scope = RoleAccess::resolveVisibilityScope($user?->role?->name, $user?->role?->visibility_scopes, 'leads');

        return Lead::query()
            ->when(
                $user !== null && ! $user->isAdmin() && $scope !== 'all',
                fn ($query) => $query->where('responsible_id', $user->id)
            )
            ->latest('id')
            ->limit(200)
            ->get(['id', 'number', 'title'])
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'number' => $lead->number,
                'title' => $lead->title,
            ])
            ->values()
            ->all();
    }

    private function nextTaskNumber(): string
    {
        $prefix = 'TSK-'.now()->format('ymd');
        $sequence = DB::table('tasks')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    private function syncLinkedLeadStatus(Task $task, ?int $userId): void
    {
        if ($task->lead_id === null || ! Schema::hasTable('leads')) {
            return;
        }

        $targetLeadStatus = TaskStatus::leadStatusByTaskStatus($task->status);
        if ($targetLeadStatus === null) {
            return;
        }

        DB::table('leads')
            ->where('id', $task->lead_id)
            ->update([
                'status' => $targetLeadStatus,
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);
    }
}
