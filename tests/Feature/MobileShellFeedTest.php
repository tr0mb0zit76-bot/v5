<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileShellFeedTest extends TestCase
{
    private function createUserWithAreas(array $areas, array $scopes = []): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'mobile-shell-'.uniqid(),
            'display_name' => 'Mobile Shell',
            'visibility_areas' => json_encode($areas),
            'visibility_scopes' => json_encode($scopes),
            'columns_config' => json_encode([]),
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }

    public function test_mobile_shell_tasks_returns_open_tasks_for_responsible_user(): void
    {
        $user = $this->createUserWithAreas(['tasks'], ['tasks' => 'own']);
        $other = User::factory()->create();

        Task::query()->create([
            'number' => 'TSK-MOB-1',
            'title' => 'Моя открытая задача',
            'status' => 'in_progress',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'number' => 'TSK-MOB-2',
            'title' => 'Чужая задача',
            'status' => 'in_progress',
            'priority' => 'medium',
            'responsible_id' => $other->id,
            'created_by' => $other->id,
        ]);

        Task::query()->create([
            'number' => 'TSK-MOB-3',
            'title' => 'Завершённая',
            'status' => 'done',
            'priority' => 'medium',
            'responsible_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.tasks'))
            ->assertOk()
            ->assertJsonPath('overdue_count', 0)
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Моя открытая задача');
    }

    public function test_mobile_shell_orders_returns_recent_orders_for_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'MOB-1001',
            'is_active' => true,
        ]);

        Order::factory()->create([
            'manager_id' => User::factory()->create()->id,
            'order_number' => 'MOB-9999',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.orders'))
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.order_number', 'MOB-1001')
            ->assertJsonStructure([
                'orders' => [[
                    'documents_pending_count',
                    'documents_total_count',
                    'documents_url',
                ]],
            ]);
    }

    public function test_mobile_shell_order_summary_returns_document_checklist(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'MOB-SUM-1',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.orders.summary', $order))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.order_number', 'MOB-SUM-1')
            ->assertJsonStructure([
                'order',
                'documents' => ['pending_count', 'completed_count', 'total_count', 'pending'],
                'urls' => ['order', 'documents'],
            ]);
    }

    public function test_mobile_shell_order_summary_forbidden_for_other_manager(): void
    {
        $manager = $this->createUserWithAreas(['orders'], ['orders' => 'own']);
        $other = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->getJson(route('mobile.shell.orders.summary', $order))
            ->assertForbidden();
    }

    public function test_mobile_shell_documents_returns_recent_document_chips(): void
    {
        $user = $this->createUserWithAreas(['orders', 'documents'], ['orders' => 'own', 'documents' => 'own']);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.documents'))
            ->assertOk()
            ->assertJsonStructure([
                'recent',
                'attention',
            ]);
    }
}
