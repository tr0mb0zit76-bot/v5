<?php

namespace Tests\Unit;

use App\Services\Mcp\McpCrossDomainGuard;
use App\Services\McpIntegrationService;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class McpCrossDomainGuardTest extends TestCase
{
    public function test_guard_is_permissive_when_no_links_configured(): void
    {
        if (! Schema::hasTable('mcp_data_links')) {
            $this->markTestSkipped('mcp_data_links table is not migrated.');
        }

        app(McpIntegrationService::class)->syncLinks([]);

        app(McpCrossDomainGuard::class)->enforce('get_order');

        $this->assertTrue(true);
    }

    public function test_guard_allows_tool_when_cross_domain_link_exists(): void
    {
        if (! Schema::hasTable('mcp_data_links')) {
            $this->markTestSkipped('mcp_data_links table is not migrated.');
        }

        app(McpIntegrationService::class)->syncLinks([
            [
                'source_key' => 'contractors',
                'target_key' => 'orders',
                'bidirectional' => true,
                'is_active' => true,
            ],
            [
                'source_key' => 'fleet',
                'target_key' => 'orders',
                'bidirectional' => true,
                'is_active' => true,
            ],
        ]);

        app(McpCrossDomainGuard::class)->enforce('get_order');

        $this->assertTrue(true);
    }

    public function test_guard_blocks_tool_when_cross_domain_link_missing(): void
    {
        if (! Schema::hasTable('mcp_data_links')) {
            $this->markTestSkipped('mcp_data_links table is not migrated.');
        }

        app(McpIntegrationService::class)->syncLinks([
            [
                'source_key' => 'contractors',
                'target_key' => 'orders',
                'bidirectional' => true,
                'is_active' => true,
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Обмен данными между MCP-доменами «orders» и «fleet» не разрешён');

        app(McpCrossDomainGuard::class)->enforce('get_order');
    }
}
