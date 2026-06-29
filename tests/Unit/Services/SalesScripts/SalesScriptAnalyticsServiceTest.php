<?php

namespace Tests\Unit\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\SalesScriptAnalyticsService;
use Database\Seeders\SalesScriptsDemoSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesScriptAnalyticsServiceTest extends TestCase
{
    public function test_reaction_matrix_calculates_success_and_lost_rates(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            $this->markTestSkipped('Sales scripts tables unavailable.');
        }

        $this->seed(SalesScriptsDemoSeeder::class);

        $versionId = (int) SalesScriptVersion::query()->value('id');
        $qualifyNode = SalesScriptNode::query()->where('client_key', 'qualify')->firstOrFail();
        $priceObjectionId = (int) SalesScriptReactionClass::query()->where('key', 'price_objection')->value('id');
        $positiveId = (int) SalesScriptReactionClass::query()->where('key', 'positive_signal')->value('id');
        $user = User::factory()->create();

        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $priceObjectionId, SalesPlaySessionOutcome::Lost, 12);
        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $priceObjectionId, SalesPlaySessionOutcome::Lost, 11);
        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $priceObjectionId, SalesPlaySessionOutcome::Lost, 10);
        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $positiveId, SalesPlaySessionOutcome::Progress, 9);
        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $positiveId, SalesPlaySessionOutcome::Won, 8);

        $service = app(SalesScriptAnalyticsService::class);
        $report = $service->reportForVersion($versionId, 30);

        $priceRow = collect($report['reaction_matrix'])
            ->first(fn (array $row): bool => (int) $row['node_id'] === (int) $qualifyNode->id
                && (int) $row['reaction_class_id'] === $priceObjectionId);

        $this->assertNotNull($priceRow);
        $this->assertSame(3, $priceRow['transition_count']);
        $this->assertSame(100.0, $priceRow['lost_rate_pct']);

        $positiveRow = collect($report['reaction_matrix'])
            ->first(fn (array $row): bool => (int) $row['node_id'] === (int) $qualifyNode->id
                && (int) $row['reaction_class_id'] === $positiveId);

        $this->assertNotNull($positiveRow);
        $this->assertSame(100.0, $positiveRow['success_rate_pct']);
    }

    public function test_play_choice_hints_require_minimum_sample_and_two_branches(): void
    {
        if (! Schema::hasTable('sales_script_play_sessions')) {
            $this->markTestSkipped('Sales scripts tables unavailable.');
        }

        config(['sales_scripts.analytics.min_sample_size' => 10]);

        $this->seed(SalesScriptsDemoSeeder::class);

        $versionId = (int) SalesScriptVersion::query()->value('id');
        $qualifyNode = SalesScriptNode::query()->where('client_key', 'qualify')->firstOrFail();
        $priceObjectionId = (int) SalesScriptReactionClass::query()->where('key', 'price_objection')->value('id');
        $positiveId = (int) SalesScriptReactionClass::query()->where('key', 'positive_signal')->value('id');
        $user = User::factory()->create();
        $service = app(SalesScriptAnalyticsService::class);

        foreach (range(1, 9) as $offset) {
            $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $priceObjectionId, SalesPlaySessionOutcome::Lost, $offset);
            $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $positiveId, SalesPlaySessionOutcome::Won, $offset + 50);
        }

        $insufficient = $service->playChoiceHints($versionId, (int) $qualifyNode->id, [$priceObjectionId, $positiveId]);
        $this->assertSame([], $insufficient);

        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $priceObjectionId, SalesPlaySessionOutcome::Lost, 99);
        $this->createCompletedSession($versionId, $user->id, $qualifyNode->id, $positiveId, SalesPlaySessionOutcome::Won, 100);

        $hints = $service->playChoiceHints($versionId, (int) $qualifyNode->id, [$priceObjectionId, $positiveId]);

        $this->assertArrayHasKey($positiveId, $hints);
        $this->assertArrayNotHasKey($priceObjectionId, $hints);
        $this->assertStringContainsString('По статистике', $hints[$positiveId]['message']);
    }

    private function createCompletedSession(
        int $versionId,
        int $userId,
        int $nodeId,
        int $reactionClassId,
        SalesPlaySessionOutcome $outcome,
        int $dayOffset,
    ): void {
        $session = SalesScriptPlaySession::query()->create([
            'user_id' => $userId,
            'sales_script_version_id' => $versionId,
            'current_node_id' => $nodeId,
            'outcome' => $outcome,
            'started_at' => now()->subDays($dayOffset),
            'completed_at' => now()->subDays($dayOffset),
            'created_at' => now()->subDays($dayOffset),
            'updated_at' => now()->subDays($dayOffset),
        ]);

        $session->events()->create([
            'type' => SalesPlayEventType::RecordedReaction,
            'sales_script_node_id' => $nodeId,
            'sales_script_reaction_class_id' => $reactionClassId,
            'meta' => ['context_tags' => ['direction' => 'domestic']],
            'created_at' => now()->subDays($dayOffset),
        ]);
    }
}
