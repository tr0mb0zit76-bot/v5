<?php

namespace App\Services\Leads;

use App\Models\Task;

class TaskLeadTemplateBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Task $task): array
    {
        return [
            'status' => 'new',
            'source' => '',
            'counterparty_id' => $task->contractor_id,
            'responsible_id' => $task->responsible_id,
            'title' => $task->title,
            'description' => $task->description,
            'link_task_id' => $task->id,
        ];
    }
}
