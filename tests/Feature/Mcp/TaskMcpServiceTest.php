<?php

namespace Tests\Feature\Mcp;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\TaskMcpService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskMcpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database unavailable: '.$exception->getMessage());
        }
    }

    public function test_admin_can_create_task_for_another_responsible(): void
    {
        if (! Schema::hasTable('tasks')) {
            $this->markTestSkipped('tasks table is not migrated.');
        }

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Администратор', 'permissions' => [], 'visibility_areas' => ['tasks']],
        );

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $responsible = User::factory()->create();

        $service = app(TaskMcpService::class);
        $result = $service->create($admin, [
            'title' => 'Проверить документы',
            'responsible_id' => $responsible->id,
            'priority' => 'high',
        ]);

        $this->assertSame('Проверить документы', $result['task']['title']);
        $this->assertSame($responsible->id, $result['task']['responsible_id']);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Проверить документы',
            'responsible_id' => $responsible->id,
            'created_by' => $admin->id,
        ]);
    }

    public function test_manager_with_own_scope_cannot_assign_task_to_colleague(): void
    {
        if (! Schema::hasTable('tasks')) {
            $this->markTestSkipped('tasks table is not migrated.');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager_mcp_test'],
            [
                'display_name' => 'Менеджер MCP',
                'permissions' => [],
                'visibility_areas' => ['tasks'],
                'visibility_scopes' => ['tasks' => 'own'],
            ],
        );

        $manager = User::factory()->create(['role_id' => $role->id]);
        $colleague = User::factory()->create();

        $service = app(TaskMcpService::class);

        $this->expectException(AuthenticationException::class);
        $service->create($manager, [
            'title' => 'Чужая задача',
            'responsible_id' => $colleague->id,
        ]);
    }

    public function test_create_task_rejects_inaccessible_order_for_manager_scope(): void
    {
        if (! Schema::hasTable('tasks')) {
            $this->markTestSkipped('tasks table is not migrated.');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager_orders_own'],
            [
                'display_name' => 'Менеджер',
                'permissions' => [],
                'visibility_areas' => ['tasks', 'orders'],
                'visibility_scopes' => ['tasks' => 'all', 'orders' => 'own'],
            ],
        );

        $manager = User::factory()->create(['role_id' => $role->id]);
        $otherManager = User::factory()->create();
        $order = Order::factory()->create(['manager_id' => $otherManager->id]);

        $service = app(TaskMcpService::class);

        $this->expectException(AuthenticationException::class);
        $service->create($manager, [
            'title' => 'Задача по чужому заказу',
            'responsible_id' => $manager->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_create_task_links_accessible_order(): void
    {
        if (! Schema::hasTable('tasks')) {
            $this->markTestSkipped('tasks table is not migrated.');
        }

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Администратор', 'permissions' => [], 'visibility_areas' => ['tasks', 'orders']],
        );

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $order = Order::factory()->create(['manager_id' => $admin->id]);

        $service = app(TaskMcpService::class);
        $result = $service->create($admin, [
            'title' => 'По своему заказу',
            'responsible_id' => $admin->id,
            'order_id' => $order->id,
        ]);

        $this->assertSame($order->id, $result['task']['order_id']);
    }
}
