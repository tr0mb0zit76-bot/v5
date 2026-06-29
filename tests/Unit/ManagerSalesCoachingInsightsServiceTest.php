<?php

namespace Tests\Unit;

use App\Enums\LeadCloseOutcomeFlag;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\ManagerDealSignalExtractor;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagerSalesCoachingInsightsServiceTest extends TestCase
{
    #[Test]
    public function it_detects_hygiene_gaps_on_lost_lead(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('leads table is missing.');
        }

        $lead = Lead::factory()->create([
            'status' => 'lost',
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostNoAuthority->value,
            'lead_qualification' => ['need' => 'FTL'],
            'updated_at' => now(),
        ]);

        $extractor = new ManagerDealSignalExtractor(new ActivityLedgerService);
        $signal = $extractor->extract($lead->fresh());

        $this->assertContains('no_authority', $signal['hygiene_gaps']);
    }

    #[Test]
    public function it_returns_coaching_insights_for_leads_user(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('leads table is missing.');
        }

        $user = User::factory()->create([
            'role_id' => Role::query()->create([
                'name' => 'sales',
                'display_name' => 'Sales',
                'permissions' => [],
                'visibility_areas' => ['leads'],
            ])->id,
        ]);

        Lead::factory()->create([
            'responsible_id' => $user->id,
            'status' => 'lost',
            'close_outcome_primary_flag' => LeadCloseOutcomeFlag::LostGhosting->value,
            'updated_at' => now(),
        ]);

        $service = new ManagerSalesCoachingInsightsService(new ManagerDealSignalExtractor(new ActivityLedgerService));
        $result = $service->insights($user, 90);

        $this->assertTrue($result['available']);
        $this->assertSame(1, $result['summary']['closed_leads']);
        $this->assertNotEmpty($result['recommendations']);
    }
}
