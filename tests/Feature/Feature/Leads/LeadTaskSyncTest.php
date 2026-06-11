<?php

namespace Tests\Feature\Feature\Leads;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadTaskSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['tasks', 'leads', 'users', 'roles']);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->string('title');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable();
            $table->string('title');
            $table->string('status', 50)->default('new');
            $table->string('priority', 50)->default('medium');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
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
