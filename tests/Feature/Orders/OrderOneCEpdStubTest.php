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

class OrderOneCEpdStubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);
    }

    public function test_clerk_can_create_fake_etrn_stub(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $clerk = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'all');
        [$order] = $this->makeFarmserviceOrder($clerk, 'EPD-ETRN-1');

        $response = $this->actingAs($clerk)->postJson(
            route('orders.one-c.etrn.store', $order),
        );

        $response->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('document.document_type', OrderOneCDocument::TYPE_ETRN)
            ->assertJsonPath('document.status', OrderOneCDocument::STATUS_CREATED)
            ->assertJsonPath('epd.etrn.can_create', true)
            ->assertJsonPath('epd.expedition_receipt.document_type', OrderOneCDocument::TYPE_EXPEDITION_RECEIPT);

        $this->assertDatabaseHas('order_one_c_documents', [
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_ETRN,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'counterparty_inn' => '2312178145',
        ]);

        $order->refresh();
        if (Schema::hasColumn('orders', 'accounting_handoff_at')) {
            $this->assertNull($order->accounting_handoff_at);
        }
    }

    public function test_clerk_can_create_fake_expedition_receipt_stub(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $clerk = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'all');
        [$order] = $this->makeFarmserviceOrder($clerk, 'EPD-EXP-1');

        $this->actingAs($clerk)
            ->postJson(route('orders.one-c.expedition-receipt.store', $order))
            ->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('document.document_type', OrderOneCDocument::TYPE_EXPEDITION_RECEIPT);

        $this->assertDatabaseHas('order_one_c_documents', [
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_EXPEDITION_RECEIPT,
            'status' => OrderOneCDocument::STATUS_CREATED,
        ]);
    }

    public function test_epd_and_realization_can_coexist(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $clerk = $this->makeUserWithRole('clerk', 'Делопроизводитель', 'all');
        [$order] = $this->makeFarmserviceOrder($clerk, 'EPD-DUAL-1', '95000.00');

        $this->actingAs($clerk)->postJson(route('orders.one-c.realization.store', $order))->assertOk();
        $this->actingAs($clerk)->postJson(route('orders.one-c.etrn.store', $order))->assertOk();
        $this->actingAs($clerk)->postJson(route('orders.one-c.expedition-receipt.store', $order))->assertOk();

        $this->assertSame(3, OrderOneCDocument::query()->where('order_id', $order->id)->count());
        $this->assertTrue(
            OrderOneCDocument::query()
                ->where('order_id', $order->id)
                ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
                ->exists()
        );
    }

    public function test_manager_cannot_create_epd_stub(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing — run migrate');
        }

        $manager = $this->makeUserWithRole('manager', 'Менеджер', 'own');
        [$order] = $this->makeFarmserviceOrder($manager, 'EPD-MGR-1');

        $this->actingAs($manager)
            ->postJson(route('orders.one-c.etrn.store', $order))
            ->assertForbidden();
    }

    /**
     * @return array{0: Order, 1: Contractor}
     */
    private function makeFarmserviceOrder(User $manager, string $orderNumber, string $rate = '10000.00'): array
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
            'loading_date' => '2026-06-12',
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
