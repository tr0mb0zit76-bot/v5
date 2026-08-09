<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

    public function test_manager_can_create_fake_realization_link(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $manager = $this->makeManager();
        [$order] = $this->makeFarmserviceOrder($manager, 'АС-ТД-107', '95000.00');

        $response = $this->actingAs($manager)->postJson(
            route('orders.one-c.realization.store', $order),
        );

        $response->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('realization.status', OrderOneCDocument::STATUS_CREATED)
            ->assertJsonPath('realization.amount', '95000.00')
            ->assertJsonPath('realization.counterparty_inn', '2312178145');

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

    public function test_second_call_without_force_is_idempotent(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $manager = $this->makeManager();
        [$order] = $this->makeFarmserviceOrder($manager, 'АС-ТД-213', '290000.00');

        $this->actingAs($manager)->postJson(route('orders.one-c.realization.store', $order))->assertOk();

        $second = $this->actingAs($manager)->postJson(route('orders.one-c.realization.store', $order));
        $second->assertOk()->assertJsonPath('created', false);

        $this->assertSame(1, OrderOneCDocument::query()->where('order_id', $order->id)->count());
    }

    public function test_foreign_manager_forbidden(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $owner = $this->makeManager();
        $stranger = $this->makeManager();
        [$order] = $this->makeFarmserviceOrder($owner, 'АС-ТД-486', '330000.00');

        $this->actingAs($stranger)
            ->postJson(route('orders.one-c.realization.store', $order))
            ->assertForbidden();
    }

    public function test_disabled_integration_returns_validation_error(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        config(['one_c.enabled' => false]);

        $manager = $this->makeManager();
        [$order] = $this->makeFarmserviceOrder($manager, 'АС-ТД-107', '95000.00');

        $this->actingAs($manager)
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

    private function makeManager(): User
    {
        $roleId = DB::table('roles')->where('name', 'manager')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'permissions' => json_encode([], JSON_THROW_ON_ERROR),
                'visibility_areas' => json_encode(['orders'], JSON_THROW_ON_ERROR),
                'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create([
            'role_id' => $roleId,
            'is_active' => true,
        ]);
    }
}
