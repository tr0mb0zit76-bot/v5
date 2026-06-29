<?php

namespace Tests\Unit;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Services\BusinessProcessPlaybookSeederService;
use Tests\TestCase;

class BusinessProcessPlaybookSeederServiceTest extends TestCase
{
    public function test_seeder_fills_default_transport_intake_playbook(): void
    {
        $process = BusinessProcess::query()->where('slug', 'transport-intake')->firstOrFail();

        $stage = BusinessProcessStage::query()
            ->where('business_process_id', $process->id)
            ->where('name', 'Получение деталей по перевозке')
            ->firstOrFail();

        $stage->forceFill([
            'stage_goal' => null,
            'description' => null,
            'success_criteria' => null,
        ])->saveQuietly();

        $result = app(BusinessProcessPlaybookSeederService::class)->seed(true);

        $this->assertGreaterThanOrEqual(1, $result['stages']);

        $stage->refresh();
        $this->assertNotNull($stage->stage_goal);
        $this->assertStringContainsString('Действия менеджера', (string) $stage->description);
        $this->assertNotNull($stage->success_criteria);
    }
}
