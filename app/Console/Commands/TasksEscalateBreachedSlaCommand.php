<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Services\CabinetNotifier;
use App\Services\TaskSlaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('tasks:escalate-breached-sla')]
#[Description('Уведомляет руководителей о задачах с просроченным SLA (разово на задачу, пока SLA не снят).')]
class TasksEscalateBreachedSlaCommand extends Command
{
    public function __construct(
        private readonly TaskSlaService $taskSlaService,
        private readonly CabinetNotifier $cabinetNotifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('tasks')
            || ! Schema::hasColumn('tasks', 'sla_deadline_at')
            || ! Schema::hasColumn('tasks', 'sla_escalated_at')) {
            $this->warn('Таблица tasks или колонки SLA отсутствуют.');

            return self::SUCCESS;
        }

        $tasks = Task::query()
            ->where('status', '!=', 'done')
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<', now())
            ->whereNull('sla_escalated_at')
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            if (! $this->taskSlaService->isSlaBreached($task)) {
                continue;
            }

            $this->cabinetNotifier->notifyTaskSlaBreached($task);

            $task->forceFill(['sla_escalated_at' => now()])->saveQuietly();

            if (Schema::hasTable('task_events')) {
                TaskEvent::query()->create([
                    'task_id' => $task->id,
                    'user_id' => null,
                    'type' => 'sla_escalated',
                    'title' => 'Эскалация по SLA',
                    'description' => 'Руководители уведомлены о просроченном SLA.',
                    'meta' => null,
                ]);
            }

            $count++;
        }

        $this->info("Обработано задач: {$count}");

        return self::SUCCESS;
    }
}
