<?php

namespace Tests\Unit\Services\Leads;

use App\Models\BusinessProcess;
use App\Models\BusinessProcessStage;
use App\Models\Lead;
use App\Models\LeadCargoItem;
use App\Models\LeadRoutePoint;
use App\Models\Task;
use App\Services\ActivityLedgerService;
use App\Services\LeadBusinessProcessService;
use App\Services\Leads\LeadOperationalBriefService;
use App\Support\LeadDataChecks;
use App\Support\LeadStageRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadOperationalBriefServiceTest extends TestCase
{
    public function test_data_checks_detect_missing_route_and_cargo(): void
    {
        $lead = $this->makeLead([
            'counterparty_id' => 1,
            'status' => 'qualification',
        ]);

        $checks = LeadDataChecks::run($lead);

        $this->assertTrue($checks['has_counterparty']);
        $this->assertFalse($checks['has_route']);
        $this->assertFalse($checks['has_cargo']);
    }

    #[DataProvider('statusFallbackRequirementsProvider')]
    public function test_stage_requirements_fallback_by_status(string $status, array $expectedBlocking): void
    {
        $requirements = LeadStageRequirements::forLead($status, null, null);

        $this->assertSame($expectedBlocking, $requirements['blocking']);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function statusFallbackRequirementsProvider(): array
    {
        return [
            'qualification' => ['qualification', ['has_counterparty', 'has_route', 'has_cargo']],
            'calculation' => ['calculation', ['has_route', 'has_cargo', 'has_client_price']],
            'won' => ['won', ['close_outcome_set']],
        ];
    }

    public function test_brief_lists_blocking_gaps_for_empty_qualification_lead(): void
    {
        $service = new LeadOperationalBriefService(
            $this->createMock(LeadBusinessProcessService::class),
            $this->createMock(ActivityLedgerService::class),
        );

        $lead = $this->makeLead([
            'number' => 'LD-TEST-001',
            'status' => 'qualification',
            'title' => 'Тестовый лид',
        ]);

        $brief = $service->build($lead);

        $this->assertSame('stuck', $brief['health']);
        $this->assertContains('no_counterparty', collect($brief['gaps'])->pluck('code')->all());
        $this->assertContains('no_route', collect($brief['gaps'])->pluck('code')->all());
        $this->assertNotEmpty($brief['actions_now']);
        $this->assertStringContainsString('LD-TEST-001', $brief['summary_ru']);
    }

    public function test_brief_ready_when_intake_data_complete(): void
    {
        $processService = $this->createMock(LeadBusinessProcessService::class);
        $processService->method('isStageOverdue')->willReturn(false);

        $service = new LeadOperationalBriefService(
            $processService,
            $this->createMock(ActivityLedgerService::class),
        );

        $lead = $this->makeLead([
            'number' => 'LD-TEST-002',
            'status' => 'qualification',
            'counterparty_id' => 5,
            'lead_qualification' => ['authority' => 'Директор'],
            'next_contact_at' => now(),
        ]);
        $lead->setRelation('routePoints', collect([
            new LeadRoutePoint(['address' => 'Москва', 'type' => 'loading']),
        ]));
        $lead->setRelation('cargoItems', collect([
            new LeadCargoItem(['name' => 'Паллеты']),
        ]));
        $lead->setRelation('tasks', collect([
            new Task(['status' => 'in_progress']),
        ]));
        $lead->setRelation('offers', collect());

        $brief = $service->build($lead);

        $this->assertSame('ready_to_advance', $brief['health']);
        $this->assertEmpty(collect($brief['gaps'])->where('severity', 'blocking'));
    }

    public function test_brief_uses_transport_intake_stage_requirements(): void
    {
        $processService = $this->createMock(LeadBusinessProcessService::class);
        $processService->method('isStageOverdue')->willReturn(false);

        $service = new LeadOperationalBriefService(
            $processService,
            $this->createMock(ActivityLedgerService::class),
        );

        $lead = $this->makeLead([
            'number' => 'LD-TEST-003',
            'status' => 'qualification',
            'counterparty_id' => 2,
        ]);
        $lead->setRelation('businessProcess', new BusinessProcess([
            'name' => 'Получение деталей',
            'slug' => 'transport-intake',
        ]));
        $lead->setRelation('businessProcessStage', new BusinessProcessStage([
            'name' => 'Расчёт цены',
            'stage_goal' => 'Подготовить КП',
        ]));

        $brief = $service->build($lead);

        $this->assertSame('stuck', $brief['health']);
        $this->assertContains('no_client_price', collect($brief['gaps'])->pluck('code')->all());
        $this->assertSame('Расчёт цены', $brief['context']['bp_stage_name']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeLead(array $attributes): Lead
    {
        $lead = new Lead($attributes);
        $lead->id = $attributes['id'] ?? 1;
        $lead->setRelation('routePoints', collect());
        $lead->setRelation('cargoItems', collect());
        $lead->setRelation('offers', collect());
        $lead->setRelation('tasks', collect());
        $lead->setRelation('businessProcess', null);
        $lead->setRelation('businessProcessStage', null);

        return $lead;
    }
}
