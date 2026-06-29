<?php

namespace Tests\Unit;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\LeadProcessStageLog;
use App\Services\ActivityLedgerService;
use App\Services\BusinessProcessAnalyticsService;
use App\Services\LeadBusinessProcessService;
use Tests\TestCase;

class BusinessProcessAnalyticsServiceTest extends TestCase
{
    public function test_health_overview_flags_missing_playbook_and_bottleneck(): void
    {
        $process = BusinessProcess::query()->create([
            'name' => 'Тестовая воронка',
            'slug' => 'test-funnel',
            'is_active' => true,
        ]);

        $stageA = BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Квалификация',
            'sequence' => 10,
            'duration_days' => 2,
        ]);

        BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Расчёт',
            'sequence' => 20,
            'duration_days' => 3,
            'description' => 'Есть playbook',
        ]);

        $lead = Lead::query()->create([
            'number' => 'L-001',
            'title' => 'Тест',
            'status' => 'new',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $stageA->id,
            'stage_entered_at' => now()->subDays(5),
        ]);

        LeadProcessStageLog::query()->create([
            'lead_id' => $lead->id,
            'business_process_stage_id' => $stageA->id,
            'entered_at' => now()->subDays(10),
            'exited_at' => now()->subDays(4),
            'due_at' => now()->subDays(8),
        ]);

        $service = new BusinessProcessAnalyticsService(new LeadBusinessProcessService(app(ActivityLedgerService::class)));

        $health = $service->healthOverview(90);

        $processHealth = collect($health['processes'])->firstWhere('name', 'Тестовая воронка');
        $this->assertNotNull($processHealth);
        $this->assertNotEmpty($health['recommendations']);

        $messages = array_column($health['recommendations'], 'message');
        $this->assertTrue(
            collect($messages)->contains(fn (string $message): bool => str_contains($message, 'без инструкции')),
        );
    }
}
