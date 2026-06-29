<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\LeadLinkedTaskService;
use Tests\TestCase;

class LeadLinkedTaskServiceTest extends TestCase
{
    public function test_cancels_open_tasks_when_lead_is_lost(): void
    {
        $user = User::query()->create([
            'name' => 'Менеджер',
            'email' => 'manager@example.com',
            'password' => bcrypt('secret'),
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-1',
            'status' => 'lost',
            'title' => 'Лид',
        ]);

        $openTask = Task::query()->create([
            'number' => 'TSK-1',
            'title' => 'Перезвонить',
            'status' => 'in_progress',
            'lead_id' => $lead->id,
        ]);

        $doneTask = Task::query()->create([
            'number' => 'TSK-2',
            'title' => 'Уже сделано',
            'status' => 'done',
            'lead_id' => $lead->id,
        ]);

        $cancelled = (new LeadLinkedTaskService)->cancelOpenTasksForLostLead($lead, $user);

        $this->assertSame(1, $cancelled);
        $this->assertSame('cancelled', $openTask->fresh()->status);
        $this->assertSame('done', $doneTask->fresh()->status);
        $this->assertSame('lead_lost', $openTask->fresh()->meta['cancelled_reason'] ?? null);
    }
}
