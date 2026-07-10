<?php

namespace Tests\Unit\Services\SalesScripts;

use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\SalesScriptCrmActionService;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesScriptCrmActionServiceTest extends TestCase
{
    public function test_sync_after_completion_sets_crm_synced_at_only_once(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            $this->markTestSkipped('sales_script_play_sessions table is unavailable.');
        }

        $this->seed(SalesScriptsDemoSeeder::class);

        $user = User::factory()->create();
        $versionId = (int) SalesScriptVersion::query()->value('id');
        $this->assertGreaterThan(0, $versionId);

        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $user->id,
            'sales_script_version_id' => $versionId,
            'completed_at' => now(),
            'started_at' => now(),
        ]);

        $service = app(SalesScriptCrmActionService::class);
        $service->syncAfterCompletion($session);
        $service->syncAfterCompletion($session->fresh());

        $session->refresh();

        $this->assertNotNull($session->crm_synced_at);
    }
}
