<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskViewAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskViewAuthorizationTest extends TestCase
{
    public function test_department_scope_allows_colleague_task(): void
    {
        if (! Schema::hasTable('department_user') || ! Schema::hasTable('departments')) {
            $this->markTestSkipped('department tables are unavailable.');
        }

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Tasks dept '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'dept_tasks_'.uniqid(),
        ], [
            'display_name' => 'Dept tasks',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['tasks'],
            'visibility_scopes' => ['tasks' => 'department'],
        ]);

        $role->update([
            'visibility_areas' => ['tasks'],
            'visibility_scopes' => ['tasks' => 'department'],
        ]);

        $colleague = User::factory()->create(['role_id' => $role->id]);
        $viewer = User::factory()->create(['role_id' => $role->id]);

        DB::table('department_user')->insert([
            [
                'department_id' => $departmentId,
                'user_id' => $colleague->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'department_id' => $departmentId,
                'user_id' => $viewer->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $task = new Task([
            'responsible_id' => $colleague->id,
        ]);

        $this->assertTrue(TaskViewAuthorization::userCanViewTask($viewer, $task));
        $this->assertTrue(TaskViewAuthorization::userCanAssignToUser($viewer, (int) $colleague->id));
    }
}
