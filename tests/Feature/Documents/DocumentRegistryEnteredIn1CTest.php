<?php

namespace Tests\Feature\Documents;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentRegistryEnteredIn1CTest extends TestCase
{
    public function test_manager_can_mark_order_as_entered_in_1c(): void
    {
        $manager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'DOC-1C-1',
        ]);

        $this->actingAs($manager)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'да',
            ])
            ->assertOk()
            ->assertJson(['entered_in_1c' => 'да']);

        $order->refresh();

        $this->assertNotNull($order->accounting_handoff_at);
        $this->assertSame($manager->id, $order->accounting_handoff_by);
    }

    public function test_manager_can_clear_entered_in_1c_flag(): void
    {
        $manager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'accounting_handoff_at' => now(),
            'accounting_handoff_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'нет',
            ])
            ->assertOk()
            ->assertJson(['entered_in_1c' => 'нет']);

        $order->refresh();

        $this->assertNull($order->accounting_handoff_at);
        $this->assertNull($order->accounting_handoff_by);
    }

    public function test_foreign_manager_cannot_update_entered_in_1c(): void
    {
        $manager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);
        $otherManager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $otherManager->id,
            'order_number' => 'DOC-1C-FOREIGN',
        ]);

        $this->actingAs($manager)
            ->patchJson(route('documents.orders.entered-in-1c', $order), [
                'entered_in_1c' => 'да',
            ])
            ->assertForbidden();
    }

    public function test_documents_index_includes_entered_in_1c_column(): void
    {
        $manager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'accounting_handoff_at' => now(),
            'accounting_handoff_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.order_id', $order->id)
                ->where('rows.0.entered_in_1c', 'да'),
            );
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $roleId = DB::table('roles')->where('name', 'manager')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'permissions' => json_encode([], JSON_THROW_ON_ERROR),
                'visibility_areas' => json_encode($areas, JSON_THROW_ON_ERROR),
                'visibility_scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
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
