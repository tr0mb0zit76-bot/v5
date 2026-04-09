<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Requests\StoreTaskChecklistItemRequest;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklistItem;
use App\Models\TaskEvent;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'attachmentBaseUrl' => route('tasks.index'),
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

        $this->logTaskEvent($task, $request->user()?->id, 'created', 'Создана задача', $task->title);
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

        $this->logTaskEvent($task, $request->user()?->id, 'updated', 'Обновлены поля задачи', $task->title);
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

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'status_changed',
            'Изменён статус задачи',
            TaskStatus::label($task->status)
        );
        $this->syncLinkedLeadStatus($task, $request->user()?->id);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
        ]);
    }

    public function storeChecklistItem(StoreTaskChecklistItemRequest $request, Task $task): RedirectResponse
    {
        $item = $task->checklistItems()->create([
            'title' => $request->string('title')->toString(),
            'created_by' => $request->user()?->id,
        ]);

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'checklist_added',
            'Добавлен пункт чеклиста',
            $item->title
        );

        return to_route('tasks.index');
    }

    public function toggleChecklistItem(Request $request, Task $task, TaskChecklistItem $taskChecklistItem): RedirectResponse
    {
        abort_unless($taskChecklistItem->task_id === $task->id, 404);
        abort_unless(RoleAccess::canMutateTask($request->user(), $task), 403);

        $isDone = ! $taskChecklistItem->is_done;
        $taskChecklistItem->update([
            'is_done' => $isDone,
            'completed_by' => $isDone ? $request->user()?->id : null,
            'completed_at' => $isDone ? now() : null,
        ]);

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            $isDone ? 'checklist_done' : 'checklist_reopened',
            $isDone ? 'Пункт чеклиста выполнен' : 'Пункт чеклиста снова открыт',
            $taskChecklistItem->title
        );

        return to_route('tasks.index');
    }

    public function storeComment(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $comment = $task->comments()->create([
            'user_id' => $request->user()?->id,
            'body' => $request->string('body')->toString(),
        ]);

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'comment_added',
            'Добавлен комментарий',
            mb_strimwidth($comment->body, 0, 140, '...')
        );

        return to_route('tasks.index');
    }

    public function storeAttachment(StoreTaskAttachmentRequest $request, Task $task): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('tasks/attachments', 'public');

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'attachment_added',
            'Добавлено вложение',
            $attachment->original_name
        );

        return to_route('tasks.index');
    }

    public function destroyAttachment(Request $request, Task $task, TaskAttachment $taskAttachment): RedirectResponse
    {
        abort_unless($taskAttachment->task_id === $task->id, 404);
        abort_unless(RoleAccess::canMutateTask($request->user(), $task), 403);

        Storage::disk($taskAttachment->disk)->delete($taskAttachment->path);

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'attachment_deleted',
            'Удалено вложение',
            $taskAttachment->original_name
        );

        $taskAttachment->delete();

        return to_route('tasks.index');
    }

    public function downloadAttachment(Request $request, Task $task, TaskAttachment $taskAttachment): BinaryFileResponse
    {
        abort_unless($taskAttachment->task_id === $task->id, 404);
        abort_unless($this->canAccessTaskRow($request, $task), 403);

        return Storage::disk($taskAttachment->disk)->download($taskAttachment->path, $taskAttachment->original_name);
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

        $taskQuery = Task::query()
            ->with([
                'responsible:id,name',
                'lead:id,number,title',
            ])
            ->when(
                $user !== null && ! $user->isAdmin() && $scope !== 'all',
                fn ($query) => $query->where('responsible_id', $user->id)
            )
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderBy('due_at')
            ->orderByDesc('id');

        if (Schema::hasTable('task_checklist_items')) {
            $taskQuery->with('checklistItems:id,task_id,title,is_done,completed_at');
        }

        if (Schema::hasTable('task_comments')) {
            $taskQuery->with(['comments:id,task_id,user_id,body,created_at', 'comments.user:id,name']);
        }

        if (Schema::hasTable('task_attachments')) {
            $taskQuery->with(['attachments:id,task_id,user_id,disk,path,original_name,mime_type,size_bytes,created_at', 'attachments.user:id,name']);
        }

        if (Schema::hasTable('task_events')) {
            $taskQuery->with(['events:id,task_id,user_id,type,title,description,created_at', 'events.user:id,name']);
        }

        return $taskQuery
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
                'checklist_items' => $task->relationLoaded('checklistItems') ? $task->checklistItems->map(fn ($item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'is_done' => (bool) $item->is_done,
                    'completed_at' => optional($item->completed_at)?->toIso8601String(),
                ])->values()->all() : [],
                'comments' => $task->relationLoaded('comments') ? $task->comments->map(fn ($comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author_name' => $comment->user?->name,
                    'created_at' => optional($comment->created_at)?->toIso8601String(),
                ])->values()->all() : [],
                'attachments' => $task->relationLoaded('attachments') ? $task->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'author_name' => $attachment->user?->name,
                    'created_at' => optional($attachment->created_at)?->toIso8601String(),
                    'download_url' => route('tasks.attachments.download', [$task, $attachment]),
                    'delete_url' => route('tasks.attachments.destroy', [$task, $attachment]),
                ])->values()->all() : [],
                'events' => $task->relationLoaded('events') ? $task->events->map(fn ($event): array => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'description' => $event->description,
                    'author_name' => $event->user?->name,
                    'created_at' => optional($event->created_at)?->toIso8601String(),
                ])->values()->all() : [],
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

    private function canAccessTaskRow(Request $request, Task $task): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')
            && ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'kanban')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'tasks');

        return $scope === 'all' || (int) $task->responsible_id === (int) $user->id;
    }

    private function logTaskEvent(
        Task $task,
        ?int $userId,
        string $type,
        string $title,
        ?string $description = null,
        ?array $meta = null,
    ): void {
        if (! Schema::hasTable('task_events')) {
            return;
        }

        TaskEvent::query()->create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
