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
        Schema::dropIfExists('task_events');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_attachments');
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

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('task_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
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
                ->has('tasks', 1)
                ->where('selectedTask', null));
    }

    public function test_tasks_show_includes_selected_task(): void
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
            'number' => 'TSK-TEST-SHOW',
            'title' => 'Детальный просмотр',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('selectedTask.id', $task->id)
                ->where('selectedTask.title', 'Детальный просмотр'));
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

    public function test_patch_task_status_redirects_when_inertia_header_present(): void
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

        $task = Task::query()->create([
            'number' => 'TSK-TEST-INERTIA',
            'title' => 'Канбан Inertia',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('kanban.index'))
            ->withHeaders(['X-Inertia' => 'true'])
            ->patch(route('tasks.status.update', $task), [
                'status' => 'review',
            ])
            ->assertRedirect();

        $this->assertSame('review', $task->fresh()->status);
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

    public function test_task_detail_actions_create_checklist_comment_and_event(): void
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
            'number' => 'TSK-TEST-003',
            'title' => 'Phase 2',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('tasks.checklist-items.store', $task), [
                'title' => 'Проверить документы',
            ])
            ->assertRedirect(route('tasks.index'));

        $this->actingAs($user)
            ->post(route('tasks.comments.store', $task), [
                'body' => 'Комментарий к задаче',
            ])
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('task_checklist_items', [
            'task_id' => $task->id,
            'title' => 'Проверить документы',
        ]);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'body' => 'Комментарий к задаче',
        ]);

        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'type' => 'checklist_added',
        ]);

        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'type' => 'comment_added',
        ]);
    }
}
