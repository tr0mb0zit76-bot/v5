<?php

namespace App\Services\Mcp;

use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TaskMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
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
                    ->orWhere('number', 'like', '%'.$needle.'%');

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
