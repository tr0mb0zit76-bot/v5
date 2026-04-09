<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tasks');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('new');
            $table->string('priority', 20)->default('medium');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_tasks_index_forbidden_without_visibility(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'viewer',
            'display_name' => 'Viewer',
            'visibility_areas' => json_encode(['dashboard']),
            'visibility_scopes' => json_encode([]),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertForbidden();
    }

    public function test_tasks_index_renders_tasks_for_supervisor(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => json_encode(['tasks', 'kanban']),
            'visibility_scopes' => json_encode(['tasks' => 'all']),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
        ]);

        Task::query()->create([
            'number' => 'TSK-TEST-001',
            'title' => 'Проверка',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->has('tasks', 1));
    }

    public function test_store_task_creates_row(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => json_encode(['tasks']),
            'visibility_scopes' => json_encode(['tasks' => 'all']),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
        ]);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'title' => 'Новая задача',
                'description' => null,
                'priority' => 'high',
                'status' => 'new',
                'due_at' => null,
                'responsible_id' => $user->id,
                'lead_id' => null,
            ])
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('tasks', [
            'title' => 'Новая задача',
            'status' => 'new',
            'responsible_id' => $user->id,
        ]);
    }

    public function test_patch_task_status_returns_json(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => json_encode(['tasks']),
            'visibility_scopes' => json_encode(['tasks' => 'all']),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
        ]);

        $task = Task::query()->create([
            'number' => 'TSK-TEST-002',
            'title' => 'Канбан',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('tasks.status.update', $task), [
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('task.status', 'in_progress');

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_kanban_accessible_with_kanban_visibility_only(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'custom',
            'display_name' => 'Custom',
            'visibility_areas' => json_encode(['kanban']),
            'visibility_scopes' => json_encode([]),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
        ]);

        $this->actingAs($user)
            ->get(route('kanban.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kanban/Index')
                ->where('canMutateTasks', false));
    }
}
