<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_orders_matching_number_not_full_list(): void
    {
        $manager = $this->makeManagerUser();

        $match = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'AA-CHAIN-100',
        ]);
        Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'AA-OTHER-999',
        ]);

        $response = $this->actingAs($manager)->getJson(route('orders.link-search', [
            'q' => 'CHAIN',
            'exclude_order_id' => $match->id,
        ]));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $peer = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'GR-CHAIN-200',
        ]);

        $response = $this->actingAs($manager)->getJson(route('orders.link-search', [
            'q' => 'CHAIN',
            'exclude_order_id' => $match->id,
        ]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($peer->id, $ids);
        $this->assertNotContains($match->id, $ids);
    }

    public function test_search_requires_min_two_characters(): void
    {
        $manager = $this->makeManagerUser();

        $this->actingAs($manager)
            ->getJson(route('orders.link-search', ['q' => 'A']))
            ->assertStatus(422);
    }

    public function test_can_link_and_unlink_expedition_chain(): void
    {
        $manager = $this->makeManagerUser();
        $left = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'AA-L-1',
        ]);
        $right = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'GR-R-1',
        ]);

        $store = $this->actingAs($manager)->postJson(route('orders.links.store', $left), [
            'linked_order_id' => $right->id,
        ]);

        $store->assertOk();
        $store->assertJsonPath('linked_order.id', $right->id);
        $store->assertJsonPath('linked_order.order_number', 'GR-R-1');

        $this->assertDatabaseHas('order_links', [
            'order_id' => min($left->id, $right->id),
            'linked_order_id' => max($left->id, $right->id),
            'link_type' => OrderLink::TYPE_EXPEDITION_CHAIN,
        ]);

        $fromPeer = $this->actingAs($manager)->get(route('orders.edit', $right));
        $fromPeer->assertOk();
        $fromPeer->assertInertia(fn ($page) => $page
            ->component('Orders/Wizard')
            ->where('order.linked_order.id', $left->id)
            ->where('order.linked_order.order_number', 'AA-L-1'));

        $unlink = $this->actingAs($manager)->deleteJson(route('orders.links.destroy', $right));
        $unlink->assertOk();
        $unlink->assertJsonPath('linked_order', null);
        $this->assertDatabaseCount('order_links', 0);
    }

    public function test_cannot_link_order_that_already_has_peer(): void
    {
        $manager = $this->makeManagerUser();
        $a = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'A-1']);
        $b = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'B-1']);
        $c = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'C-1']);

        $this->actingAs($manager)->postJson(route('orders.links.store', $a), [
            'linked_order_id' => $b->id,
        ])->assertOk();

        $this->actingAs($manager)->postJson(route('orders.links.store', $a), [
            'linked_order_id' => $c->id,
        ])->assertStatus(422);

        $this->actingAs($manager)->postJson(route('orders.links.store', $c), [
            'linked_order_id' => $b->id,
        ])->assertStatus(422);
    }

    public function test_search_excludes_already_linked_orders(): void
    {
        $manager = $this->makeManagerUser();
        $a = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'LINK-A']);
        $b = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'LINK-B']);
        $free = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'LINK-FREE']);
        $current = Order::factory()->create(['manager_id' => $manager->id, 'order_number' => 'CUR-1']);

        OrderLink::query()->create([
            'order_id' => min($a->id, $b->id),
            'linked_order_id' => max($a->id, $b->id),
            'link_type' => OrderLink::TYPE_EXPEDITION_CHAIN,
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->getJson(route('orders.link-search', [
            'q' => 'LINK',
            'exclude_order_id' => $current->id,
        ]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($free->id, $ids);
        $this->assertNotContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids);
        $this->assertNotContains($current->id, $ids);
    }

    private function makeManagerUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['orders']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Order Link Manager',
            'email' => 'order-link-manager-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
