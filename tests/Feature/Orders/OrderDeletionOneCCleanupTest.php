<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Orders\OrderDeletionService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderDeletionOneCCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);
    }

    public function test_deleting_order_removes_unposted_one_c_link(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing');
        }

        $user = $this->makeManager();
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'status' => 'new',
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'external_number' => '0000-000083',
            'created_by' => $user->id,
        ]);

        app(OrderDeletionService::class)->delete(
            $order,
            fn (Order $target): Order => $target->fresh() ?? $target,
        );

        $order->refresh();
        $this->assertTrue($order->trashed());
        $this->assertDatabaseMissing('order_one_c_documents', ['order_id' => $order->id]);
    }

    public function test_deleting_order_blocked_when_realization_posted(): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            $this->markTestSkipped('order_one_c_documents missing');
        }

        $user = $this->makeManager();
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'status' => 'new',
        ]);

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => '11111111-1111-1111-1111-111111111111',
            'external_number' => '0000-000001',
            'created_by' => $user->id,
        ]);

        try {
            app(OrderDeletionService::class)->delete(
                $order,
                fn (Order $target): Order => $target->fresh() ?? $target,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('one_c', $e->errors());
        }

        $this->assertDatabaseHas('order_one_c_documents', [
            'order_id' => $order->id,
            'status' => OrderOneCDocument::STATUS_CREATED,
        ]);
        $this->assertFalse(($order->fresh() ?? $order)->trashed());
    }

    private function makeManager(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Менеджер',
                'permissions' => [],
                'visibility_areas' => ['orders'],
                'visibility_scopes' => ['orders' => 'own'],
            ],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
