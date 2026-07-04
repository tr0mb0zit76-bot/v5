<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileEntityChipTest extends TestCase
{
    private function createUserWithAreas(array $areas, array $scopes = []): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'mobile-entity-'.uniqid(),
            'display_name' => 'Mobile Entity',
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

    private function createManagerUser(array $areas, array $scopes): User
    {
        $role = DB::table('roles')->where('name', 'manager')->first();

        if ($role === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'visibility_areas' => json_encode($areas),
                'visibility_scopes' => json_encode($scopes),
                'columns_config' => json_encode([]),
                'permissions' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = (int) $role->id;
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }

    public function test_entity_chips_returns_orders_and_documents(): void
    {
        $user = $this->createManagerUser(['orders', 'documents'], ['orders' => 'own', 'documents' => 'own']);

        Order::factory()->create([
            'manager_id' => $user->id,
            'order_number' => 'ENT-5001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('mobile.shell.entity-chips', ['kind' => 'order']))
            ->assertOk();

        $entities = $response->json('entities');
        $this->assertNotEmpty($entities);
        $this->assertSame('order', $entities[0]['kind']);
        $this->assertSame('ENT-5001', $entities[0]['label']);
    }

    public function test_order_document_slots_requires_manage_access(): void
    {
        $manager = $this->createManagerUser(['orders', 'documents'], ['orders' => 'own', 'documents' => 'own']);
        $other = $this->createUserWithAreas(['orders'], ['orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('mobile.shell.orders.document-slots', $order))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonStructure(['order', 'slots']);

        $this->actingAs($other)
            ->getJson(route('mobile.shell.orders.document-slots', $order))
            ->assertForbidden();
    }
}
