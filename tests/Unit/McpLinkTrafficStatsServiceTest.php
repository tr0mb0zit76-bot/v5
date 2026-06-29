<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Mcp\McpLinkTrafficStatsService;
use App\Support\AiInteractionEventType;
use App\Support\AiInteractionFeature;
use App\Support\AiInteractionOutcome;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class McpLinkTrafficStatsServiceTest extends TestCase
{
    public function test_stats_aggregate_cross_domain_tool_calls_by_edge(): void
    {
        $userId = User::factory()->create()->id;

        $this->insertToolEvent($userId, 'get_order', true, 4);
        $this->insertToolEvent($userId, 'get_order', false, 1);
        $this->insertToolEvent($userId, 'search_orders', true, 3);

        $stats = app(McpLinkTrafficStatsService::class)->forPeriod(7);

        $edgeKey = 'contractors|orders';

        $this->assertSame(8, $stats['total_calls']);
        $this->assertSame(5, $stats['edges'][$edgeKey]['calls']);
        $this->assertSame(1, $stats['edges'][$edgeKey]['errors']);
        $this->assertSame('get_order', $stats['edges'][$edgeKey]['top_tools'][0]['tool']);
        $this->assertSame(8, $stats['nodes']['orders']['calls']);
        $this->assertSame(5, $stats['nodes']['contractors']['calls']);
        $this->assertSame(5, $stats['nodes']['fleet']['calls']);
    }

    public function test_stats_ignore_tools_without_cross_domain_pairs(): void
    {
        $userId = User::factory()->create()->id;

        $this->insertToolEvent($userId, 'search_contractors', true, 2);

        $stats = app(McpLinkTrafficStatsService::class)->forPeriod(7);

        $this->assertSame(2, $stats['total_calls']);
        $this->assertSame([], $stats['edges']);
        $this->assertSame(2, $stats['nodes']['contractors']['calls']);
    }

    private function insertToolEvent(int $userId, string $toolName, bool $ok, int $times): void
    {
        for ($index = 0; $index < $times; $index++) {
            DB::table('ai_interaction_events')->insert([
                'user_id' => $userId,
                'feature' => AiInteractionFeature::Mcp->value,
                'event_type' => AiInteractionEventType::ToolInvoked->value,
                'outcome' => $ok ? AiInteractionOutcome::Success->value : AiInteractionOutcome::Failed->value,
                'ok' => $ok,
                'tool_name' => $toolName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
