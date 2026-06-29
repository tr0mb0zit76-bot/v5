<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\ManagerDealSignalExtractor;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagerSalesCoachingWinRateTest extends TestCase
{
    public function test_single_lost_lead_has_zero_win_rate(): void
    {
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['leads']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'role_id' => $adminRoleId,
            'name' => 'Админ',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);

        Lead::query()->create([
            'number' => 'LD-LOST-1',
            'status' => 'lost',
            'title' => 'Проигранный лид',
            'responsible_id' => $user->id,
            'updated_at' => now(),
        ]);

        $service = new ManagerSalesCoachingInsightsService(
            new ManagerDealSignalExtractor($this->createMock(ActivityLedgerService::class)),
        );

        $insights = $service->insights($user, 90);

        $this->assertTrue($insights['available']);
        $this->assertSame(1, $insights['summary']['closed_leads']);
        $this->assertSame(0, $insights['summary']['won_leads']);
        $this->assertSame(1, $insights['summary']['lost_leads']);
        $this->assertSame(0.0, $insights['summary']['win_rate_pct']);
    }
}
