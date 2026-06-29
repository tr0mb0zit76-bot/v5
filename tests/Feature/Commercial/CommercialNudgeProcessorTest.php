<?php

namespace Tests\Feature\Commercial;

use App\Models\Lead;
use App\Models\User;
use App\Support\CommercialNudgeType;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommercialNudgeProcessorTest extends TestCase
{
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
