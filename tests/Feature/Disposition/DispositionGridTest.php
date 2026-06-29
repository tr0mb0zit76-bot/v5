<?php

namespace Tests\Feature\Disposition;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Tests\Support\CreatesInTransitOrders;
use Tests\TestCase;

class DispositionGridTest extends TestCase
{
    use CreatesInTransitOrders;

    public function test_disposition_index_requires_orders_visibility(): void
    {
        $user = $this->makeUser(['dashboard']);

        $this->actingAs($user)
            ->get(route('disposition.index'))
            ->assertForbidden();
    }

    public function test_upsert_disposition_entry_for_in_progress_order(): void
    {
        $user = $this->makeUser(['orders'], ['orders' => 'all']);

        $order = $this->createInTransitOrder(['manager_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(route('disposition.entries.upsert'), [
            'order_id' => $order->id,
            'date' => '2026-05-28',
            'slot' => 'morning',
            'location' => 'Новосибирск',
            'comment' => 'На погрузке',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('entry.location', 'Новосибирск')
            ->assertJsonPath('entry.comment', 'На погрузке');

        $this->assertDatabaseHas('disposition_entries', [
            'order_id' => $order->id,
            'date' => '2026-05-28',
            'slot' => 'morning',
            'location' => 'Новосибирск',
            'recorded_by' => $user->id,
        ]);
    }

    public function test_upsert_denied_for_order_not_in_progress(): void
    {
        $user = $this->makeUser(['orders'], ['orders' => 'all']);

        $order = Order::factory()->create([
            'status' => 'closed',
            'manager_id' => $user->id,
        ]);

        $this->actingAs($user)->postJson(route('disposition.entries.upsert'), [
            'order_id' => $order->id,
            'date' => '2026-05-28',
            'slot' => 'evening',
            'location' => 'Томск',
        ])->assertForbidden();
    }

    public function test_upsert_updates_existing_slot(): void
    {
        $user = $this->makeUser(['orders'], ['orders' => 'all']);

        $order = $this->createInTransitOrder(['manager_id' => $user->id]);

        DispositionEntry::query()->create([
            'order_id' => $order->id,
            'date' => '2026-05-28',
            'slot' => 'morning',
            'location' => 'Старый',
            'recorded_by' => $user->id,
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)->postJson(route('disposition.entries.upsert'), [
            'order_id' => $order->id,
            'date' => '2026-05-28',
            'slot' => 'morning',
            'location' => 'Новый',
            'comment' => null,
        ])->assertOk();

        $this->assertSame(1, DispositionEntry::query()->where('order_id', $order->id)->count());
        $this->assertDatabaseHas('disposition_entries', [
            'order_id' => $order->id,
            'location' => 'Новый',
        ]);
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'disposition_test_'.uniqid(),
            'display_name' => 'Disposition Test',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
