<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class TaskSlaService
{
    /**
     * SLA по умолчанию совпадает со сроком задачи; явное значение перекрывает.
     */
    public function resolveSlaDeadline(?string $dueAt, ?string $explicitSlaAt): ?Carbon
    {
        if ($explicitSlaAt !== null && $explicitSlaAt !== '') {
            return Carbon::parse($explicitSlaAt);
        }

        if ($dueAt !== null && $dueAt !== '') {
            return Carbon::parse($dueAt);
        }

        return null;
    }

    public function isOpen(Task $task): bool
    {
        return $task->status !== 'done';
    }

    public function isSlaBreached(Task $task): bool
    {
        if (! $this->isOpen($task) || $task->sla_deadline_at === null) {
            return false;
        }

        return $task->sla_deadline_at->isPast();
    }

    /**
     * Сбросить отметку эскалации, если нарушение SLA снято или задача закрыта.
     */
    public function clearEscalationIfResolved(Task $task): void
    {
        if (! Schema::hasColumn('tasks', 'sla_escalated_at')) {
            return;
        }

        if ($task->sla_escalated_at === null) {
            return;
        }

        if ($task->status === 'done' || ! $this->isSlaBreached($task)) {
            $task->forceFill(['sla_escalated_at' => null])->saveQuietly();
        }
    }
}
