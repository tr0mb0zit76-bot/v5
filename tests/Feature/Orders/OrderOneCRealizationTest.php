<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderOneCRealizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);
    }

    public function test_clerk_can_create_fake_realization_link(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $clerk = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'all');
        [$order] = $this->makeFarmserviceOrder($clerk, 'АС-ТД-107', '95000.00');

        $response = $this->actingAs($clerk)->postJson(
            route('orders.one-c.realization.store', $order),
        );

        $response->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('realization.status', OrderOneCDocument::STATUS_CREATED)
            ->assertJsonPath('realization.amount', '95000.00')
            ->assertJsonPath('realization.counterparty_inn', '2312178145')
            ->assertJsonPath('one_c.can_create', true);

        $this->assertDatabaseHas('order_one_c_documents', [
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'counterparty_inn' => '2312178145',
            'counterparty_kpp' => '231201001',
        ]);

        $order->refresh();
        if (Schema::hasColumn('orders', 'accounting_handoff_at')) {
            $this->assertNotNull($order->accounting_handoff_at);
        }
    }

    public function test_accountant_can_create_fake_realization_link(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $accountant = $this->makeUserWithRole('accountant', 'Бухгалтер', 'all');
        [$order] = $this->makeFarmserviceOrder($accountant, 'АС-ТД-213', '290000.00');

        $this->actingAs($accountant)
            ->postJson(route('orders.one-c.realization.store', $order))
            ->assertOk()
            ->assertJsonPath('created', true);
    }

    public function test_manager_cannot_create_realization(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $manager = $this->makeUserWithRole('manager', 'Менеджер', 'own');
        [$order] = $this->makeFarmserviceOrder($manager, 'АС-ТД-107', '95000.00');

        $this->actingAs($manager)
            ->postJson(route('orders.one-c.realization.store', $order))
            ->assertForbidden();
    }

    public function test_second_call_without_force_is_idempotent(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $clerk = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'all');
        [$order] = $this->makeFarmserviceOrder($clerk, 'АС-ТД-213', '290000.00');

        $this->actingAs($clerk)->postJson(route('orders.one-c.realization.store', $order))->assertOk();

        $second = $this->actingAs($clerk)->postJson(route('orders.one-c.realization.store', $order));
        $second->assertOk()->assertJsonPath('created', false);

        $this->assertSame(1, OrderOneCDocument::query()->where('order_id', $order->id)->count());
    }

    public function test_foreign_clerk_without_order_scope_forbidden(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $owner = $this->makeUserWithRole('manager', 'Менеджер', 'own');
        $clerkOwnOnly = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'own');
        [$order] = $this->makeFarmserviceOrder($owner, 'АС-ТД-486', '330000.00');

        $this->actingAs($clerkOwnOnly)
            ->postJson(route('orders.one-c.realization.store', $order))
            ->assertForbidden();
    }

    public function test_disabled_integration_returns_validation_error(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        config(['one_c.enabled' => false]);

        $clerk = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'all');
        [$order] = $this->makeFarmserviceOrder($clerk, 'АС-ТД-107', '95000.00');

        $this->actingAs($clerk)
            ->postJson(route('orders.one-c.realization.store', $order))
            ->assertStatus(422);
    }

    /**
     * @return array{0: Order, 1: Contractor}
     */
    private function makeFarmserviceOrder(User $manager, string $orderNumber, string $rate): array
    {
        $client = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО "ФАРМСЕРВИС"',
            'inn' => '2312178145',
            'kpp' => '231201001',
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'customer_id' => $client->id,
            'order_number' => $orderNumber,
            'customer_rate' => $rate,
            'order_date' => '2026-06-11',
            'unloading_date' => '2026-06-18',
            'status' => 'documents',
        ]);

        return [$order, $client];
    }

    private function makeUserWithRole(string $name, string $displayName, string $ordersScope): User
    {
        $role = Role::query()->where('name', $name)->first();

        if ($role === null) {
            $role = Role::query()->create([
                'name' => $name,
                'display_name' => $displayName,
                'permissions' => [],
                'visibility_areas' => ['orders', 'documents'],
                'visibility_scopes' => ['orders' => $ordersScope, 'documents' => $ordersScope],
            ]);
        } else {
            $role->forceFill([
                'visibility_areas' => ['orders', 'documents'],
                'visibility_scopes' => ['orders' => $ordersScope, 'documents' => $ordersScope],
            ])->save();
        }

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
