<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Commercial\ManagerDealSignalExtractor;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagerSalesCoachingWinRateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['leads', 'users', 'roles']);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
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

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->string('title');
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->json('lead_qualification')->nullable();
            $table->timestamp('proposal_sent_at')->nullable();
            $table->timestamp('next_contact_at')->nullable();
            $table->string('close_outcome_primary_flag')->nullable();
            $table->timestamps();
        });
    }

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
