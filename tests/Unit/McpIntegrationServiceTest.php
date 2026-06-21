<?php

namespace Tests\Unit;

use App\Services\McpIntegrationService;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class McpIntegrationServiceTest extends TestCase
{
    public function test_can_exchange_data_when_link_exists(): void
    {
        if (! Schema::hasTable('mcp_data_links')) {
            $this->markTestSkipped('mcp_data_links table is not migrated.');
        }

        $service = app(McpIntegrationService::class);

        $service->syncLinks([
            [
                'source_key' => 'sales_book',
                'target_key' => 'leads',
                'bidirectional' => true,
                'is_active' => true,
            ],
        ]);

        $this->assertTrue($service->canExchangeData('sales_book', 'leads'));
        $this->assertTrue($service->canExchangeData('leads', 'sales_book'));
        $this->assertFalse($service->canExchangeData('sales_book', 'orders'));
    }

    public function test_legacy_drivers_area_expands_own_fleet_components(): void
    {
        $areas = RoleAccess::expandLegacyOwnFleetVisibilityAreas(['drivers']);

        $this->assertContains('fleet_trips', $areas);
        $this->assertContains('fleet_efficiency', $areas);
    }
}
