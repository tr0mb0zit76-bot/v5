<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderBasedOnTemplateBuilder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderBasedOnTemplateTest extends TestCase
{
    public function test_builder_copies_customer_route_and_cargo_without_performers(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create();
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'inn' => '7700000000',
            'type' => 'customer',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'SRC-1',
            'manager_id' => $manager->id,
            'customer_id' => $customerId,
            'status' => 'in_progress',
            'performers' => json_encode([['fleet_vehicle_id' => 9, 'fleet_driver_id' => 8]], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
            'description' => 'leg_1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('route_points')->insert([
            'order_leg_id' => $legId,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Москва, склад 1',
            'normalized_data' => json_encode(['city' => 'Москва'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cargos')->insert([
            'order_id' => $orderId,
            'title' => 'Бетон',
            'description' => 'М300',
            'weight' => 12000,
            'volume' => 45,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $template = app(OrderBasedOnTemplateBuilder::class)->build(Order::query()->with('client')->findOrFail($orderId));

        $this->assertSame($customerId, $template['client_id']);
        $this->assertSame([], $template['performers']);
        $this->assertCount(1, $template['route_points']);
        $this->assertSame('loading', $template['route_points'][0]['type']);
        $this->assertSame('Москва', $template['route_points'][0]['normalized_data']['city']);
        $this->assertCount(1, $template['cargo_items']);
        $this->assertSame('Бетон', $template['cargo_items'][0]['name']);
        $this->assertNull($template['financial_term']['client_price'] ?? null);
    }
}
