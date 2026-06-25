<?php

namespace App\Services\Mcp;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Services\TaskSlaService;
use App\Support\TaskNumberGenerator;
use App\Support\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TaskMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly TaskNumberGenerator $taskNumbers,
        private readonly TaskSlaService $taskSlaService,
        private readonly CabinetNotifier $cabinetNotifier,
    ) {}

    /**
     * @return array{tasks: list<array<string, mixed>>, total: int}
     */
    public function search(User $user, string $query, int $limit = 15): array
    {
        $this->access->requireTasksArea($user);

        if (! Schema::hasTable('tasks')) {
            return ['tasks' => [], 'total' => 0];
        }

        $needle = trim($query);
        $limit = max(1, min($limit, 25));

        $builder = Task::query()
            ->with([
                'responsible:id,name',
                'contractor:id,name',
            ])
            ->orderByDesc('id');

        $this->access->applyTasksScope($builder, $user);

        if ($needle !== '') {
            $builder->where(function (Builder $scoped) use ($needle): void {
                $scoped->where('title', 'like', '%'.$needle.'%')
                    ->orWhere('number', 'like', '%'.$needle.'%')
                    ->orWhereHas('responsible', function (Builder $userQuery) use ($needle): void {
                        $userQuery->where('name', 'like', '%'.$needle.'%');
                    });

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $scoped->orWhere('id', (int) $needle);
                }
            });
        }

        $tasks = $builder->limit($limit)->get();

        return [
            'tasks' => $tasks->map(fn (Task $task): array => $this->summarize($task))->all(),
            'total' => $tasks->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(User $user, int $taskId): array
    {
        $this->access->requireTasksArea($user);

        $builder = Task::query()
            ->with([
                'responsible:id,name',
                'creator:id,name',
                'lead:id,number,title',
                'contractor:id,name',
            ]);

        $this->access->applyTasksScope($builder, $user);

        /** @var Task $task */
        $task = $builder->whereKey($taskId)->firstOrFail();

        return $this->detail($task);
    }

    /**
     * @param  array{
     *     title: string,
     *     responsible_id: int,
     *     priority?: string,
     *     description?: string|null,
     *     due_at?: string|null,
     *     order_id?: int|null,
     *     lead_id?: int|null,
     *     contractor_id?: int|null,
     *     meta?: array<string, mixed>|null
     * }  $payload
     * @return array{task: array<string, mixed>}
     */
    public function create(User $user, array $payload): array
    {
        if (! Schema::hasTable('tasks')) {
            throw new RuntimeException('Таблица задач недоступна.');
        }

        $responsibleId = (int) $payload['responsible_id'];
        $this->access->ensureCanCreateTask($user, $responsibleId);

        if (! User::query()->whereKey($responsibleId)->exists()) {
            throw ValidationException::withMessages([
                'responsible_id' => 'Указанный ответственный не найден.',
            ]);
        }

        $orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : null;
        if ($orderId !== null && $orderId > 0) {
            $this->access->findAccessibleOrder($user, $orderId);
        } else {
            $orderId = null;
        }

        $leadId = isset($payload['lead_id']) ? (int) $payload['lead_id'] : null;
        if ($leadId !== null && $leadId > 0 && ! Lead::query()->whereKey($leadId)->exists()) {
            throw ValidationException::withMessages([
                'lead_id' => 'Лид не найден.',
            ]);
        }

        $contractorId = isset($payload['contractor_id']) ? (int) $payload['contractor_id'] : null;
        if ($contractorId !== null && $contractorId > 0) {
            $contractorQuery = Contractor::query()->whereKey($contractorId);
            $this->access->applyContractorsScope($contractorQuery, $user);

            if (! $contractorQuery->exists()) {
                throw ValidationException::withMessages([
                    'contractor_id' => 'Контрагент не найден или недоступен.',
                ]);
            }
        }

        $priority = (string) ($payload['priority'] ?? 'medium');
        $dueAt = $payload['due_at'] ?? null;

        $attributes = [
            'number' => $this->taskNumbers->next(),
            'title' => trim((string) $payload['title']),
            'description' => isset($payload['description']) ? trim((string) $payload['description']) : null,
            'status' => 'new',
            'priority' => $priority,
            'due_at' => $dueAt,
            'responsible_id' => $responsibleId,
            'created_by' => $user->id,
            'lead_id' => $leadId,
            'order_id' => $orderId,
            'contractor_id' => $contractorId,
        ];

        if (Schema::hasColumn('tasks', 'meta') && isset($payload['meta'])) {
            $attributes['meta'] = $payload['meta'];
        }

        if (Schema::hasColumn('tasks', 'sla_deadline_at')) {
            $attributes['sla_deadline_at'] = $this->taskSlaService->resolveSlaDeadline(
                $dueAt !== null ? (string) $dueAt : null,
                null,
            );
        }

        $task = Task::query()->create($attributes);
        $this->logTaskCreated($task, $user->id);
        $this->cabinetNotifier->notifyTaskAssigned($task, $user);

        return ['task' => $this->detail($task->fresh([
            'responsible:id,name',
            'creator:id,name',
            'lead:id,number,title',
            'contractor:id,name',
        ]))];
    }

    private function logTaskCreated(Task $task, int $userId): void
    {
        if (! Schema::hasTable('task_events')) {
            return;
        }

        TaskEvent::query()->create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'type' => 'created',
            'title' => 'Создана задача',
            'description' => $task->title,
            'meta' => ['source' => 'mcp'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Task $task): array
    {
        return [
            'id' => $task->id,
            'number' => $task->number,
            'title' => $task->title,
            'status' => $task->status,
            'status_label' => TaskStatus::label($task->status),
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toIso8601String(),
            'responsible_id' => $task->responsible_id,
            'responsible_name' => $task->responsible?->name,
            'order_id' => $task->order_id,
            'lead_id' => $task->lead_id,
            'contractor_id' => $task->contractor_id,
            'contractor_name' => $task->contractor?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Task $task): array
    {
        $summary = $this->summarize($task);

        $summary['description'] = $task->description;
        $summary['sla_deadline_at'] = $task->sla_deadline_at?->toIso8601String();
        $summary['completed_at'] = $task->completed_at?->toIso8601String();
        $summary['created_by'] = $task->created_by;
        $summary['creator_name'] = $task->creator?->name;
        $summary['lead_number'] = $task->lead?->number;
        $summary['lead_title'] = $task->lead?->title;

        return $summary;
    }
}
