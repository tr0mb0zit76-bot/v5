<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepairCrmStatusCommandTest extends TestCase
{
    public function test_repairs_lead_status_with_activity(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'status' => 'won',
        ]);

        $this->artisan('crm:repair-status', [
            'entity' => 'lead',
            'id' => $lead->id,
            'status' => 'lost',
            '--reason' => 'Неверное закрытие',
            '--user-id' => $user->id,
        ])->assertExitCode(0);

        $this->assertSame('lost', $lead->fresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'status_change',
            'subject' => 'Статус лида исправлен вручную',
            'created_by' => $user->id,
        ]);
    }

    public function test_repairs_task_status_with_event(): void
    {
        $user = User::factory()->create();
        $task = Task::query()->create([
            'number' => 'TSK-REPAIR-001',
            'title' => 'Проверить статус',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->artisan('crm:repair-status', [
            'entity' => 'task',
            'id' => $task->id,
            'status' => 'done',
            '--reason' => 'Ручная сверка',
            '--user-id' => $user->id,
        ])->assertExitCode(0);

        $task->refresh();

        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => 'status_repaired',
            'title' => 'Статус задачи исправлен вручную',
        ]);
    }

    public function test_dry_run_does_not_write_status(): void
    {
        $lead = Lead::factory()->create([
            'status' => 'won',
        ]);

        $this->artisan('crm:repair-status', [
            'entity' => 'lead',
            'id' => $lead->id,
            'status' => 'lost',
            '--reason' => 'Проверка',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame('won', $lead->fresh()->status);
        $this->assertSame(0, DB::table('lead_activities')
            ->where('lead_id', $lead->id)
            ->where('subject', 'Статус лида исправлен вручную')
            ->count());
    }

    public function test_reason_is_required(): void
    {
        $lead = Lead::factory()->create();

        $this->artisan('crm:repair-status', [
            'entity' => 'lead',
            'id' => $lead->id,
            'status' => 'lost',
        ])->assertExitCode(1);
    }
}
