<?php

namespace Tests\Unit\Services\Mcp;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Mcp\TaskMcpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskMcpServiceSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['tasks', 'users', 'roles', 'role_user']);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('new');
            $table->string('priority', 20)->default('medium');
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_search_matches_responsible_name_fragment(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => ['tasks'],
        ]);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $responsible = User::factory()->create(['name' => 'Тищенко Дина Владимировна']);
        $other = User::factory()->create(['name' => 'Иванов Петр']);

        Task::query()->create([
            'number' => 'T-1001',
            'title' => 'Позвонить клиенту',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $responsible->id,
            'created_by' => $admin->id,
        ]);

        Task::query()->create([
            'number' => 'T-1002',
            'title' => 'Другая задача',
            'status' => 'new',
            'priority' => 'medium',
            'responsible_id' => $other->id,
            'created_by' => $admin->id,
        ]);

        $result = app(TaskMcpService::class)->search($admin, 'Тищенко', 25);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Тищенко Дина Владимировна', $result['tasks'][0]['responsible_name']);
        $this->assertSame('Позвонить клиенту', $result['tasks'][0]['title']);
    }
}
