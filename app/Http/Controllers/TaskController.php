<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateTasksRequest;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Requests\StoreTaskChecklistItemRequest;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskDueRequest;
use App\Http\Requests\UpdateTaskInlineRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklistItem;
use App\Models\TaskEvent;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\CabinetNotifier;
use App\Services\CompanyPlanning\CompanyPlanningTaskSyncService;
use App\Services\TaskSlaService;
use App\Support\ActivityEventType;
use App\Support\LeadStatus;
use App\Support\LeadViewAuthorization;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use App\Support\TaskViewAuthorization;
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
    public function __construct(
        private readonly CabinetNotifier $cabinetNotifier,
        private readonly TaskSlaService $taskSlaService,
        private readonly ActivityLedgerService $activityLedger,
        private readonly CompanyPlanningTaskSyncService $companyPlanningTaskSync,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($this->canAccessTasks($request), 403);

        return Inertia::render('Tasks/Index', $this->tasksPageSharedData($request));
    }

    public function show(Request $request, Task $task): Response
    {
        abort_unless($this->canAccessTasks($request), 403);

        $taskModel = Task::query()
            ->with($this->taskEagerLoads())
            ->findOrFail($task->id);

        abort_unless($this->canAccessTaskRow($request, $taskModel), 403);

        return Inertia::render('Tasks/Index', array_merge($this->tasksPageSharedData($request), [
            'selectedTask' => $this->formatTaskRow($taskModel),
        ]));
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

        $attributes = [
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
        ];

        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $sla = $this->taskSlaService->resolveSlaDeadline(
                isset($validated['due_at']) ? (string) $validated['due_at'] : null,
                isset($validated['sla_deadline_at']) ? (string) $validated['sla_deadline_at'] : null,
            );
            $attributes['sla_deadline_at'] = $sla;
        }

        $task = Task::query()->create($attributes);

        $this->logTaskEvent($task, $request->user()?->id, 'created', 'Создана задача', $task->title);

        if ($task->lead_id !== null) {
            $lead = Lead::query()->find($task->lead_id);

            if ($lead !== null) {
                $this->activityLedger->record(
                    $lead,
                    ActivityEventType::TaskCreated,
                    'Создана задача',
                    $task->title,
                    ['task_id' => $task->id],
                    null,
                    $request->user(),
                    $task,
                );
            }
        }

        $this->syncLinkedLeadStatus($task, $request->user()?->id);
        $this->cabinetNotifier->notifyTaskAssigned($task, $request->user());

        return to_route('tasks.index');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $validated = $request->validated();

        $updatePayload = [
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
        ];

        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $updatePayload['sla_deadline_at'] = $this->taskSlaService->resolveSlaDeadline(
                isset($validated['due_at']) ? (string) $validated['due_at'] : null,
                isset($validated['sla_deadline_at']) && $validated['sla_deadline_at'] !== null
                    ? (string) $validated['sla_deadline_at']
                    : null,
            );
        }

        $task->update($updatePayload);

        if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
            $this->taskSlaService->clearEscalationIfResolved($task->fresh());
        }

        if ($task->wasChanged('responsible_id')) {
            $this->cabinetNotifier->notifyTaskAssigned($task->fresh(), $request->user());
        }

        $this->logTaskEvent($task, $request->user()?->id, 'updated', 'Обновлены поля задачи', $task->title);
        $this->syncLinkedLeadStatus($task, $request->user()?->id);
        $this->companyPlanningTaskSync->syncFromTaskIfTerminal($task->fresh());

        return to_route('tasks.index');
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $status = $request->string('status')->toString();
        $task->update([
            'status' => $status,
            'completed_at' => $status === 'done' ? now() : null,
        ]);

        if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
            $this->taskSlaService->clearEscalationIfResolved($task->fresh());
        }

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'status_changed',
            'Изменён статус задачи',
            TaskStatus::label($task->status)
        );
        $this->syncLinkedLeadStatus($task, $request->user()?->id);
        $this->companyPlanningTaskSync->syncFromTaskIfTerminal($task->fresh());

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
            ],
        ]);
    }

    public function inlineUpdate(UpdateTaskInlineRequest $request, Task $task): RedirectResponse
    {
        $field = $request->string('field')->toString();
        $value = $request->input('value');

        if ($field === 'priority') {
            $task->update(['priority' => (string) $value]);
            $this->logTaskEvent(
                $task,
                $request->user()?->id,
                'priority_changed',
                'Изменён приоритет',
                (string) $value,
            );
        } else {
            $assigneeId = (int) $value;
            $task->update(['responsible_id' => $assigneeId]);
            $assignee = User::query()->find($assigneeId);
            $this->logTaskEvent(
                $task->fresh(),
                $request->user()?->id,
                'assigned',
                'Назначен ответственный',
                $assignee?->name,
            );
            $this->cabinetNotifier->notifyTaskAssigned($task->fresh(), $request->user());
        }

        return back();
    }

    public function updateDue(UpdateTaskDueRequest $request, Task $task): RedirectResponse
    {
        $dueAt = Carbon::parse($request->string('due_at')->toString());
        $updatePayload = ['due_at' => $dueAt];

        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $updatePayload['sla_deadline_at'] = $this->taskSlaService->resolveSlaDeadline(
                $dueAt->toDateTimeString(),
                $task->sla_deadline_at?->toDateTimeString(),
            );
        }

        $task->update($updatePayload);

        if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
            $this->taskSlaService->clearEscalationIfResolved($task->fresh());
        }

        $this->logTaskEvent(
            $task,
            $request->user()?->id,
            'due_rescheduled',
            'Перенесён срок выполнения',
            $dueAt->format('d.m.Y H:i'),
        );

        if ($request->header('X-Inertia')) {
            return back();
        }

        return to_route('tasks.show', $task);
    }

    public function completeAndCreateFollowUp(Request $request, Task $task): RedirectResponse
    {
        abort_unless(RoleAccess::canMutateTask($request->user(), $task), 403);
        abort_if($task->status === 'done', 422, 'Задача уже завершена.');

        $userId = $request->user()?->id;
        $lastMessage = $this->resolveLastTaskMessage($task);

        $task->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
            $this->taskSlaService->clearEscalationIfResolved($task->fresh());
        }

        $this->logTaskEvent(
            $task,
            $userId,
            'status_changed',
            'Изменён статус задачи',
            TaskStatus::label('done'),
        );
        $this->syncLinkedLeadStatus($task, $userId);

        $newTaskAttributes = [
            'number' => $this->nextTaskNumber(),
            'title' => $task->title,
            'description' => $task->description,
            'status' => 'new',
            'priority' => $task->priority,
            'due_at' => $task->due_at,
            'responsible_id' => $task->responsible_id,
            'created_by' => $userId,
            'lead_id' => $task->lead_id,
            'order_id' => $task->order_id,
            'contractor_id' => $task->contractor_id,
        ];

        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $newTaskAttributes['sla_deadline_at'] = $task->sla_deadline_at;
        }

        $newTask = Task::query()->create($newTaskAttributes);

        $this->logTaskEvent($newTask, $userId, 'created', 'Создана задача', $newTask->title);

        if ($lastMessage !== null) {
            $this->logTaskEvent(
                $newTask,
                $userId,
                'continued_from_task',
                'Сообщение из задачи '.$task->number,
                $lastMessage,
                ['from_task_id' => $task->id, 'from_task_number' => $task->number],
            );
        }

        $this->syncLinkedLeadStatus($newTask, $userId);
        $this->cabinetNotifier->notifyTaskAssigned($newTask, $request->user());

        return to_route('tasks.show', $newTask);
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

        $this->cabinetNotifier->notifyTaskComment($task, $comment, $request->user());

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

    public function destroy(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        abort_unless($this->canAccessTaskRow($request, $task), 403);
        abort_unless(RoleAccess::canDeleteTask($request->user()), 403);

        $title = $task->title;
        $task->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Задача удалена.',
            ]);
        }

        return to_route('tasks.index')->with('flash', [
            'type' => 'success',
            'message' => sprintf('Задача «%s» удалена.', $title),
        ]);
    }

    public function downloadAttachment(Request $request, Task $task, TaskAttachment $taskAttachment): BinaryFileResponse
    {
        abort_unless($taskAttachment->task_id === $task->id, 404);
        abort_unless($this->canAccessTaskRow($request, $task), 403);

        return Storage::disk($taskAttachment->disk)->download($taskAttachment->path, $taskAttachment->original_name);
    }

    public function bulkUpdate(BulkUpdateTasksRequest $request): RedirectResponse
    {
        abort_unless($this->canAccessTasks($request), 403);

        $validated = $request->validated();
        $ids = $validated['task_ids'];
        $action = $validated['action'];

        $tasks = Task::query()->whereIn('id', $ids)->orderBy('id')->get();

        abort_unless($tasks->count() === count(array_unique($ids)), 404);

        foreach ($tasks as $task) {
            if ($action === 'delete') {
                abort_unless(RoleAccess::canDeleteTask($request->user()), 403);
                $task->delete();

                continue;
            }

            if ($action === 'close') {
                abort_unless(RoleAccess::canMutateTask($request->user(), $task), 403);
                $task->update([
                    'status' => 'done',
                    'completed_at' => now(),
                ]);
                $this->logTaskEvent($task->fresh(), $request->user()?->id, 'bulk_closed', 'Массовое закрытие', $task->title);
                $this->syncLinkedLeadStatus($task->fresh(), $request->user()?->id);
                $this->companyPlanningTaskSync->syncFromTaskIfTerminal($task->fresh());
                if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
                    $this->taskSlaService->clearEscalationIfResolved($task->fresh());
                }

                continue;
            }

            if ($action === 'assign') {
                abort_unless(RoleAccess::canBulkMutateTasks($request->user()), 403);
                $task->update([
                    'responsible_id' => $validated['responsible_id'],
                ]);
                $assignee = User::query()->find($validated['responsible_id']);
                $this->logTaskEvent(
                    $task->fresh(),
                    $request->user()?->id,
                    'bulk_assigned',
                    'Массовое переназначение',
                    $assignee?->name
                );
                $this->cabinetNotifier->notifyTaskAssigned($task->fresh(), $request->user());

                continue;
            }

            abort_unless(RoleAccess::canMutateTask($request->user(), $task), 403);

            if ($action === 'status') {
                $status = (string) $validated['status'];
                $task->update([
                    'status' => $status,
                    'completed_at' => $status === 'done' ? now() : null,
                ]);
                if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
                    $this->taskSlaService->clearEscalationIfResolved($task->fresh());
                }
                $this->logTaskEvent(
                    $task->fresh(),
                    $request->user()?->id,
                    'status_changed',
                    'Массовая смена статуса',
                    TaskStatus::label($status),
                );
                $this->syncLinkedLeadStatus($task->fresh(), $request->user()?->id);
                $this->companyPlanningTaskSync->syncFromTaskIfTerminal($task->fresh());

                continue;
            }

            $dueAt = Carbon::parse((string) $validated['due_at']);
            $updatePayload = ['due_at' => $dueAt];
            if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
                $updatePayload['sla_deadline_at'] = $this->taskSlaService->resolveSlaDeadline(
                    $dueAt->toDateTimeString(),
                    $task->sla_deadline_at?->toDateTimeString(),
                );
            }
            $task->update($updatePayload);
            if (Schema::hasColumn('tasks', 'sla_escalated_at')) {
                $this->taskSlaService->clearEscalationIfResolved($task->fresh());
            }
            $this->logTaskEvent(
                $task->fresh(),
                $request->user()?->id,
                'due_rescheduled',
                'Массовый перенос срока',
                $dueAt->format('d.m.Y H:i'),
            );
        }

        return to_route('tasks.index');
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

    private function canCreateLeads(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return Schema::hasTable('leads')
            && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads');
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
     * @return array<string, mixed>
     */
    private function tasksPageSharedData(Request $request): array
    {
        return [
            'tasks' => $this->taskRows($request),
            'selectedTask' => null,
            'statusOptions' => TaskStatus::options(),
            'quickFilters' => $this->quickFilters($request),
            'users' => $this->activeUsers(),
            'leadOptions' => $this->leadOptions($request),
            'contractorOptions' => $this->contractorOptions($request),
            'attachmentBaseUrl' => route('tasks.index'),
            'can_bulk_mutate_tasks' => RoleAccess::canBulkMutateTasks($request->user()),
            'can_delete_tasks' => RoleAccess::canDeleteTask($request->user()),
            'can_create_leads' => $this->canCreateLeads($request),
        ];
    }

    /**
     * @return list<string>
     */
    private function taskEagerLoads(): array
    {
        $loads = [
            'responsible:id,name',
            'lead:id,number,title',
            'contractor:id,name',
        ];

        if (Schema::hasTable('task_checklist_items')) {
            $loads[] = 'checklistItems:id,task_id,title,is_done,completed_at';
        }

        if (Schema::hasTable('task_comments')) {
            array_push($loads, 'comments:id,task_id,user_id,body,created_at', 'comments.user:id,name');
        }

        if (Schema::hasTable('task_attachments')) {
            array_push($loads, 'attachments:id,task_id,user_id,disk,path,original_name,mime_type,size_bytes,created_at', 'attachments.user:id,name');
        }

        if (Schema::hasTable('task_events')) {
            array_push($loads, 'events:id,task_id,user_id,type,title,description,created_at', 'events.user:id,name');
        }

        return $loads;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTaskRow(Task $task): array
    {
        return [
            'id' => $task->id,
            'number' => $task->number,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'status_label' => TaskStatus::label($task->status),
            'priority' => $task->priority,
            'due_at' => optional($task->due_at)?->format('Y-m-d\TH:i'),
            'sla_deadline_at' => Schema::hasColumn('tasks', 'sla_deadline_at')
                ? optional($task->sla_deadline_at)?->format('Y-m-d\TH:i')
                : null,
            'sla_breached' => Schema::hasColumn('tasks', 'sla_deadline_at')
                ? $this->taskSlaService->isSlaBreached($task)
                : false,
            'completed_at' => optional($task->completed_at)?->toIso8601String(),
            'responsible_id' => $task->responsible_id,
            'responsible_name' => $task->responsible?->name,
            'lead_id' => $task->lead_id,
            'lead_number' => $task->lead?->number,
            'lead_title' => $task->lead?->title,
            'order_id' => $task->order_id,
            'contractor_id' => $task->contractor_id,
            'contractor_name' => $task->contractor?->name,
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
        ];
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

        $taskQuery = Task::query()
            ->with($this->taskEagerLoads())
            ->when(
                $user !== null,
                fn ($query) => TaskViewAuthorization::applyTasksVisibilityScope($query, $user),
            )
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderBy('due_at')
            ->orderByDesc('id');

        return $taskQuery
            ->get()
            ->map(function (Task $task) use ($request): array {
                $row = $this->enrichTaskRowForGrid($this->formatTaskRow($task));
                $row['can_mutate'] = RoleAccess::canMutateTask($request->user(), $task);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichTaskRowForGrid(array $row): array
    {
        $open = ($row['status'] ?? '') !== 'done';
        $dueOverdue = $open
            && filled($row['due_at'] ?? null)
            && Carbon::parse((string) $row['due_at'])->isPast();
        $row['is_due_overdue'] = $dueOverdue;
        $row['is_overdue'] = $dueOverdue;

        return $row;
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
            ['label' => 'Просроченные', 'count' => $tasks->filter(
                fn (array $task): bool => (bool) ($task['is_due_overdue'] ?? false)
            )->count()],
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

        return Lead::query()
            ->when(
                $user !== null,
                fn ($query) => LeadViewAuthorization::applyLeadsVisibilityScope($query, $user),
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

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function contractorOptions(Request $request): array
    {
        if (! Schema::hasTable('contractors')) {
            return [];
        }

        $user = $request->user();

        return Contractor::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (Contractor $contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
            ])
            ->values()
            ->all();
    }

    private function resolveLastTaskMessage(Task $task): ?string
    {
        if (Schema::hasTable('task_comments')) {
            $latestComment = $task->comments()
                ->orderByDesc('id')
                ->value('body');

            if (is_string($latestComment) && trim($latestComment) !== '') {
                return trim($latestComment);
            }
        }

        if (! Schema::hasTable('task_events')) {
            return null;
        }

        $latestCommentEvent = $task->events()
            ->where('type', 'comment_added')
            ->whereNotNull('description')
            ->orderByDesc('id')
            ->value('description');

        if (is_string($latestCommentEvent) && trim($latestCommentEvent) !== '') {
            return trim($latestCommentEvent);
        }

        return null;
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

        $leadStatus = DB::table('leads')->where('id', $task->lead_id)->value('status');
        if (! is_string($leadStatus) || LeadStatus::isClosed($leadStatus)) {
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

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')
            && ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'kanban')) {
            return false;
        }

        return TaskViewAuthorization::userCanViewTask($user, $task);
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
