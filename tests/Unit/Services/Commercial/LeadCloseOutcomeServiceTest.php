<?php

namespace Tests\Unit\Services\Commercial;

use App\Enums\LeadCloseOutcomeFlag;
use App\Models\Lead;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\LeadCloseOutcomeService;
use Tests\TestCase;

class LeadCloseOutcomeServiceTest extends TestCase
{
    public function test_apply_syncs_status_from_lost_close_outcome_flag(): void
    {
        $lead = Lead::query()->create([
            'number' => 'LD-OUT-1',
            'status' => 'won',
            'title' => 'Неверный won',
            'close_outcome_primary_flag' => null,
        ]);

        $service = new LeadCloseOutcomeService($this->createMock(ActivityLedgerService::class));

        $service->apply($lead, LeadCloseOutcomeFlag::LostOther, null, 'Клиент отказался');

        $lead->refresh();

        $this->assertSame('lost', $lead->status);
        $this->assertSame('lost_other', $lead->close_outcome_primary_flag);
        $this->assertSame('Клиент отказался', $lead->lost_reason);
    }
}
