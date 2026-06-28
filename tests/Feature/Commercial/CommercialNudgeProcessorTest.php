<?php

namespace Tests\Feature\Commercial;

use App\Models\Lead;
use App\Models\User;
use App\Support\CommercialNudgeType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialNudgeProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'activity_events',
            'mail_messages',
            'mail_threads',
            'lead_offers',
            'lead_process_stage_logs',
            'tasks',
            'leads',
            'business_process_stages',
            'business_processes',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
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

        Schema::create('business_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_process_stages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_process_id');
            $table->string('name');
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedSmallInteger('duration_days')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->unsignedSmallInteger('no_reply_nudge_days')->nullable();
            $table->json('nudge_triggers')->nullable();
            $table->unsignedSmallInteger('ledger_idle_nudge_days')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('qualification');
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('title');
            $table->timestamp('next_contact_at')->nullable();
            $table->unsignedBigInteger('business_process_id')->nullable();
            $table->unsignedBigInteger('business_process_stage_id')->nullable();
            $table->timestamp('process_started_at')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('stage_due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_process_stage_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('business_process_stage_id');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('new');
            $table->string('priority')->default('medium');
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_offers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('number')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_threads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('lead_offer_id')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mail_thread_id');
            $table->string('direction')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('event_type')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function test_process_nudges_creates_task_when_offer_mail_has_no_reply(): void
    {
        $manager = $this->createManager();
        $processId = DB::table('business_processes')->insertGetId([
            'name' => 'Продажи',
            'slug' => 'sales',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('business_process_stages')->insertGetId([
            'business_process_id' => $processId,
            'name' => 'КП',
            'sequence' => 10,
            'duration_days' => 3,
            'is_terminal' => false,
            'no_reply_nudge_days' => 2,
            'nudge_triggers' => json_encode([CommercialNudgeType::NoReply->value]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-NR-1',
            'status' => 'qualification',
            'responsible_id' => $manager->id,
            'title' => 'Без ответа на КП',
            'business_process_id' => $processId,
            'business_process_stage_id' => $stageId,
            'process_started_at' => now()->subDays(5),
            'stage_entered_at' => now()->subDays(5),
        ]);

        $offerId = DB::table('lead_offers')->insertGetId([
            'lead_id' => $lead->id,
            'number' => 'КП-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mail_threads')->insert([
            'lead_id' => $lead->id,
            'lead_offer_id' => $offerId,
            'subject' => 'Коммерческое предложение',
            'last_outbound_at' => now()->subDays(3),
            'last_inbound_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('commercial:process-nudges')->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'responsible_id' => $manager->id,
            'status' => 'new',
        ]);

        $this->artisan('commercial:process-nudges')->assertSuccessful();
        $this->assertSame(1, DB::table('tasks')->where('lead_id', $lead->id)->count());
    }

    public function test_process_nudges_creates_task_for_overdue_business_process_stage(): void
    {
        $manager = $this->createManager();
        $processId = DB::table('business_processes')->insertGetId([
            'name' => 'Продажи',
            'slug' => 'sales-overdue',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('business_process_stages')->insertGetId([
            'business_process_id' => $processId,
            'name' => 'Переговоры',
            'sequence' => 10,
            'duration_days' => 2,
            'is_terminal' => false,
            'nudge_triggers' => json_encode([CommercialNudgeType::StageOverdue->value]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-SO-1',
            'status' => 'negotiation',
            'responsible_id' => $manager->id,
            'title' => 'Просрочен этап',
            'business_process_id' => $processId,
            'business_process_stage_id' => $stageId,
            'process_started_at' => now()->subDays(10),
            'stage_entered_at' => now()->subDays(10),
            'stage_due_at' => now()->subDay(),
        ]);

        $this->artisan('commercial:process-nudges')->assertSuccessful();

        $task = DB::table('tasks')->where('lead_id', $lead->id)->first();
        $this->assertNotNull($task);
        $this->assertStringContainsString('Просрочен этап', (string) $task->title);
    }

    public function test_process_nudges_creates_task_for_ledger_idle_on_stage(): void
    {
        $manager = $this->createManager();
        $processId = DB::table('business_processes')->insertGetId([
            'name' => 'Продажи',
            'slug' => 'sales-idle',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('business_process_stages')->insertGetId([
            'business_process_id' => $processId,
            'name' => 'Квалификация',
            'sequence' => 10,
            'duration_days' => 5,
            'is_terminal' => false,
            'nudge_triggers' => json_encode([CommercialNudgeType::LedgerIdle->value]),
            'ledger_idle_nudge_days' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-IDLE-1',
            'status' => 'qualification',
            'responsible_id' => $manager->id,
            'title' => 'Нет активности',
            'business_process_id' => $processId,
            'business_process_stage_id' => $stageId,
            'process_started_at' => now()->subDays(10),
            'stage_entered_at' => now()->subDays(10),
        ]);

        $this->artisan('commercial:process-nudges')->assertSuccessful();

        $task = DB::table('tasks')->where('lead_id', $lead->id)->first();
        $this->assertNotNull($task);
        $this->assertStringContainsString('активности', (string) $task->title);
    }

    private function createManager(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager-nudge-'.uniqid(),
            'visibility_areas' => json_encode(['leads']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Manager',
            'email' => 'mgr-nudge-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
        ]);
    }
}
