<?php

namespace Tests\Feature\Feature\Leads;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadTaskSyncTest extends TestCase
{
    public function test_completing_task_does_not_mark_active_lead_as_won(): void
    {
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin-active-sync',
            'visibility_areas' => json_encode(['tasks']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'role_id' => $adminRoleId,
            'name' => 'Админ',
            'email' => 'admin-active-sync@example.com',
            'password' => bcrypt('secret'),
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-SYNC-2',
            'status' => 'qualification',
            'title' => 'Активный лид',
        ]);

        $task = Task::query()->create([
            'number' => 'TSK-SYNC-2',
            'title' => 'Позвонить клиенту',
            'status' => 'in_progress',
            'lead_id' => $lead->id,
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->patchJson(route('tasks.status.update', $task), [
            'status' => 'done',
        ]);

        $response->assertOk();
        $this->assertSame('qualification', $lead->fresh()->status);
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_completing_task_does_not_overwrite_lost_lead_status(): void
    {
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['tasks']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'role_id' => $adminRoleId,
            'name' => 'Админ',
            'email' => 'admin-sync@example.com',
            'password' => bcrypt('secret'),
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-SYNC-1',
            'status' => 'lost',
            'title' => 'Закрытый лид',
        ]);

        $task = Task::query()->create([
            'number' => 'TSK-SYNC-1',
            'title' => 'Старый шаг',
            'status' => 'in_progress',
            'lead_id' => $lead->id,
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->patchJson(route('tasks.status.update', $task), [
            'status' => 'done',
        ]);

        $response->assertOk();
        $this->assertSame('lost', $lead->fresh()->status);
        $this->assertSame('done', $task->fresh()->status);
    }
}
