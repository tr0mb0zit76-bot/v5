<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderViewAuthorizationTest extends TestCase
{
    public function test_order_owner_with_own_scope_can_view_order_when_manager_differs(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = User::factory()->create();
        $manager = User::factory()->create();

        $order = new Order([
            'manager_id' => $manager->id,
            'order_owner_id' => $owner->id,
        ]);

        $this->assertTrue(OrderViewAuthorization::userOwnsOrderRecord($order, (int) $owner->id));
    }

    public function test_supervisor_can_view_any_order(): void
    {
        $supervisorRole = Role::query()->firstOrCreate([
            'name' => 'supervisor',
        ], [
            'display_name' => 'Supervisor',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
        ]);

        $foreignManager = User::factory()->create();

        $order = new Order([
            'manager_id' => $foreignManager->id,
        ]);

        $this->assertTrue(OrderViewAuthorization::userCanViewOrder($supervisor, $order));
    }

    public function test_unrelated_manager_with_own_scope_cannot_own_order_record(): void
    {
        $managerA = User::factory()->create();
        $managerB = User::factory()->create();

        $order = new Order([
            'manager_id' => $managerA->id,
        ]);

        $this->assertFalse(OrderViewAuthorization::userOwnsOrderRecord($order, (int) $managerB->id));
    }

    public function test_department_scope_allows_colleague_order(): void
    {
        if (! Schema::hasTable('department_user') || ! Schema::hasTable('departments')) {
            $this->markTestSkipped('department tables are unavailable.');
        }

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Отдел продаж '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $viewerRole = Role::query()->firstOrCreate([
            'name' => 'dept_viewer_'.uniqid(),
        ], [
            'display_name' => 'Dept viewer',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'department'],
        ]);

        $viewerRole->update([
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'department'],
        ]);

        $colleague = User::factory()->create(['role_id' => $viewerRole->id]);
        $viewer = User::factory()->create(['role_id' => $viewerRole->id]);

        DB::table('department_user')->insert([
            [
                'department_id' => $departmentId,
                'user_id' => $colleague->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'department_id' => $departmentId,
                'user_id' => $viewer->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $order = new Order([
            'manager_id' => $colleague->id,
        ]);

        $this->assertTrue(OrderViewAuthorization::userCanViewOrder($viewer, $order));
    }

    public function test_apply_orders_visibility_scope_to_query_includes_department_colleague(): void
    {
        if (! Schema::hasTable('department_user') || ! Schema::hasTable('departments')) {
            $this->markTestSkipped('department tables are unavailable.');
        }

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Query dept '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $viewerRole = Role::query()->firstOrCreate([
            'name' => 'dept_query_'.uniqid(),
        ], [
            'display_name' => 'Dept query',
            'permissions' => [],
            'columns_config' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'department'],
        ]);

        $viewerRole->update([
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'department'],
        ]);

        $colleague = User::factory()->create(['role_id' => $viewerRole->id]);
        $viewer = User::factory()->create(['role_id' => $viewerRole->id]);

        DB::table('department_user')->insert([
            [
                'department_id' => $departmentId,
                'user_id' => $colleague->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'department_id' => $departmentId,
                'user_id' => $viewer->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $colleagueOrderId = DB::table('orders')->insertGetId([
            'manager_id' => $colleague->id,
            'order_number' => 'Q-DEPT-'.uniqid(),
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $foreignOrderId = DB::table('orders')->insertGetId([
            'manager_id' => User::factory()->create()->id,
            'order_number' => 'Q-FOREIGN-'.uniqid(),
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = DB::table('orders');
        OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $viewer, 'orders');

        $visibleIds = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertContains($colleagueOrderId, $visibleIds);
        $this->assertNotContains($foreignOrderId, $visibleIds);
    }
}
