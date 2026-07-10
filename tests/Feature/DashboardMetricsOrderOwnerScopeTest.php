<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardMetricsOrderOwnerScopeTest extends TestCase
{
    public function test_own_scope_dashboard_includes_orders_where_user_is_order_owner(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $role = Role::query()->create([
            'name' => 'dash_owner_'.uniqid(),
            'display_name' => 'Dashboard owner',
            'permissions' => [],
            'visibility_areas' => ['dashboard', 'dashboard_tiles', 'orders'],
            'visibility_scopes' => ['dashboard_tiles' => 'own', 'orders' => 'own'],
        ]);

        $owner = User::factory()->create(['role_id' => $role->id]);
        $dispatcher = User::factory()->create();

        Order::factory()->create([
            'manager_id' => $dispatcher->id,
            'order_owner_id' => $owner->id,
            'order_date' => '2026-03-10',
        ]);

        $metrics = app(DashboardMetricsService::class)->forDashboard(
            $owner,
            '2026-01-01',
            '2026-12-31',
        );

        $this->assertSame(1, $metrics['total_orders']);
    }
}
