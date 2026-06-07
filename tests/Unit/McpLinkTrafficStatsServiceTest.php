<?php

namespace Tests\Unit;

use App\Services\Mcp\McpLinkTrafficStatsService;
use App\Support\AiInteractionEventType;
use App\Support\AiInteractionFeature;
use App\Support\AiInteractionOutcome;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpLinkTrafficStatsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'ai_interaction_events',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_interaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('event_type', 40);
            $table->string('channel', 24)->nullable();
            $table->string('outcome', 24)->nullable();
            $table->boolean('ok')->default(true);
            $table->string('tool_name', 80)->nullable();
            $table->char('prompt_fingerprint', 64)->nullable();
            $table->text('user_prompt_redacted')->nullable();
            $table->text('assistant_reply_redacted')->nullable();
            $table->json('tools_used')->nullable();
            $table->unsignedSmallInteger('tool_rounds')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('tokens_prompt')->nullable();
            $table->unsignedInteger('tokens_completion')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function test_stats_aggregate_cross_domain_tool_calls_by_edge(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
        $userId = DB::table('users')->insertGetId([
            'name' => 'Tester',
            'email' => 'tester2@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
