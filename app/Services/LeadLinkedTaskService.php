<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatus;
use Illuminate\Support\Facades\Schema;

final class LeadLinkedTaskService
{
    public function cancelOpenTasksForLostLead(Lead $lead, ?User $user = null): int
    {
        if (! Schema::hasTable('tasks') || $lead->id === null) {
            return 0;
        }

        $tasks = Task::query()
            ->where('lead_id', $lead->id)
            ->whereIn('status', TaskStatus::openStatuses())
            ->get();

        foreach ($tasks as $task) {
            $meta = is_array($task->meta) ? $task->meta : [];
            $meta['cancelled_reason'] = 'lead_lost';
            $meta['cancelled_at'] = now()->toIso8601String();
            if ($user !== null) {
                $meta['cancelled_by'] = $user->id;
            }

            $task->forceFill([
                'status' => 'cancelled',
                'meta' => $meta,
            ])->saveQuietly();
        }

        return $tasks->count();
    }
}
