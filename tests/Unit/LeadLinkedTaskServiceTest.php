<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\LeadLinkedTaskService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadLinkedTaskServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['tasks', 'leads', 'users']);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('lost');
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable();
            $table->string('title');
            $table->string('status', 50)->default('new');
            $table->string('priority', 50)->default('medium');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

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
