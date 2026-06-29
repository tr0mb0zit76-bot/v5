<?php

namespace Tests\Unit;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Services\ActivityLedgerService;
use App\Services\LeadBusinessProcessService;
use Tests\TestCase;

class LeadBusinessProcessProgressTest extends TestCase
{
    public function test_terminal_refusal_stage_reports_full_progress(): void
    {
        $process = BusinessProcess::query()->create([
            'name' => 'Тест',
            'slug' => 'test-process',
            'is_active' => true,
        ]);

        foreach ([
            ['name' => 'Шаг 1', 'sequence' => 10],
            ['name' => 'Шаг 2', 'sequence' => 20],
            ['name' => 'Шаг 3', 'sequence' => 30],
            ['name' => 'Отказ', 'sequence' => 40, 'is_terminal' => true, 'terminal_outcome' => 'lost'],
            ['name' => 'Подписание', 'sequence' => 50, 'is_terminal' => true, 'terminal_outcome' => 'won'],
        ] as $stageData) {
            BusinessProcessStage::query()->create([
                'business_process_id' => $process->id,
                ...$stageData,
            ]);
        }

        $refusalStage = BusinessProcessStage::query()
            ->where('business_process_id', $process->id)
            ->where('name', 'Отказ')
            ->firstOrFail();

        $lead = Lead::query()->create([
            'number' => 'LD-TEST-1',
            'status' => 'negotiation',
            'title' => 'Тестовый лид',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $refusalStage->id,
            'process_started_at' => now(),
            'stage_entered_at' => now(),
        ]);

        $payload = $this->processService()->progressPayload($lead);

        $this->assertNotNull($payload);
        $this->assertSame(100, $payload['progress_percent']);
        $this->assertTrue(
            collect($payload['stages'])->every(fn (array $stage): bool => $stage['state'] === 'completed'),
        );
    }

    public function test_progress_payload_includes_current_stage_playbook(): void
    {
        $process = BusinessProcess::query()->create([
            'name' => 'Playbook test',
            'slug' => 'playbook-test',
            'is_active' => true,
        ]);

        $stage = BusinessProcessStage::query()->create([
            'business_process_id' => $process->id,
            'name' => 'Квалификация',
            'sequence' => 10,
            'stage_goal' => 'Собрать параметры',
            'description' => '- [ ] Позвонить клиенту',
            'success_criteria' => 'Все поля заполнены',
        ]);

        $lead = Lead::query()->create([
            'number' => 'LD-PB-1',
            'status' => 'new',
            'title' => 'Лид',
            'business_process_id' => $process->id,
            'business_process_stage_id' => $stage->id,
        ]);

        $payload = $this->processService()->progressPayload($lead);

        $this->assertSame('Собрать параметры', $payload['current_stage_goal']);
        $this->assertStringContainsString('Позвонить', $payload['current_stage_playbook']);
        $this->assertSame('Все поля заполнены', $payload['current_stage_success_criteria']);
    }

    private function processService(): LeadBusinessProcessService
    {
        return new LeadBusinessProcessService($this->createMock(ActivityLedgerService::class));
    }
}
