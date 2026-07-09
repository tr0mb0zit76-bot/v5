<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\LoadBoardRateObservation;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\LoadBoard\LoadBoardCarrierPoolService;
use App\Services\LoadBoard\LoadBoardCorridorKey;
use App\Services\LoadBoard\ProcurementCaseSyncService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoadBoardTest extends TestCase
{
    public function test_seller_publishes_load_and_buyer_selects_offer(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role',
            'display_name' => 'Load board role',
            'visibility_areas' => ['load_board'],
        ]);

        $seller = User::factory()->create(['role_id' => $role->id]);
        $buyer = User::factory()->create(['role_id' => $role->id]);

        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'is_active' => true,
        ]);

        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'LB-001',
            'manager_id' => $seller->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'is_active' => true,
        ]);

        $this->actingAs($seller)
            ->get(route('load-board.index'))
            ->assertOk();

        $this->actingAs($seller)
            ->post(route('load-board.store'), [
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'priority' => 'urgent',
                'title' => 'Москва → Казань, 20 т',
                'loading_location' => 'Москва',
                'unloading_location' => 'Казань',
                'loading_date' => '2026-07-05',
                'cargo_name' => 'Оборудование',
                'ati_cargo_name' => 'Промышленное оборудование',
                'cargo_weight' => 20,
                'cargo_volume' => 82.5,
                'cargo_type_id' => 1,
                'cargo_type' => 'general',
                'cargo_type_label' => 'Общий груз',
                'pack_type_id' => 1,
                'package_type' => 'pallet',
                'pack_type_label' => 'Паллета',
                'package_count' => 18,
                'loading_type_id' => 3,
                'loading_type_code' => 'top',
                'loading_type_label' => 'Верхняя',
                'loading_type_items' => [
                    ['id' => 3, 'code' => 'top', 'label' => 'Верхняя'],
                ],
                'truck_body_type_id' => 3,
                'truck_body_type_code' => 'tent',
                'truck_body_type_label' => 'Тент',
                'truck_body_type_items' => [
                    ['id' => 3, 'code' => 'tent', 'label' => 'Тент'],
                ],
                'trailer_type_id' => 1,
                'trailer_type_code' => 'semi_trailer',
                'trailer_type_label' => 'Полуприцеп',
                'trailer_type_items' => [
                    ['id' => 1, 'code' => 'semi_trailer', 'label' => 'Полуприцеп'],
                ],
                'length' => 13.6,
                'width' => 2.45,
                'height' => 2.7,
                'is_oversized' => false,
                'is_fragile' => true,
                'hs_code' => '8479899707',
                'customer_rate' => 180000,
                'customer_rate_currency' => 'RUB',
                'target_carrier_rate' => 150000,
                'requirements' => 'Тент, верхняя загрузка',
            ])
            ->assertRedirect(route('load-board.index'));

        $post = LoadBoardPost::query()->firstOrFail();
        $this->assertSame($seller->id, $post->seller_id);
        $this->assertSame('new', $post->status);
        $this->assertSame('Промышленное оборудование', $post->ati_cargo_name);
        $this->assertSame('Тент', $post->truck_body_type_items[0]['label']);
        $this->assertSame('semi_trailer', $post->ati_cargo_payload['transport']['trailerTypes'][0]['code']);
        $this->assertSame(18, $post->ati_cargo_payload['packaging']['places']);
        $this->assertTrue($post->ati_cargo_payload['flags']['fragile']);
        $this->assertSame('8479899707', $post->ati_cargo_payload['hsCode']);

        $this->actingAs($buyer)
            ->post(route('load-board.ati.prepare', $post))
            ->assertRedirect()
            ->assertSessionHas('flash.load_board_ati_preview.ready', true)
            ->assertSessionHas('flash.load_board_ati_preview.payload.cargo.transport.truckBodyTypes.0.label', 'Тент');

        $this->actingAs($buyer)
            ->post(route('load-board.take', $post))
            ->assertRedirect();

        $post->refresh();
        $this->assertSame($buyer->id, $post->buyer_id);
        $this->assertSame('in_work', $post->status);
        $task = Task::query()->where('meta->load_board_post_id', $post->id)->firstOrFail();
        $this->assertSame($buyer->id, $task->responsible_id);
        $this->assertSame('critical', $task->priority);

        $this->actingAs($buyer)
            ->post(route('load-board.offers.store', $post), [
                'carrier_id' => $carrier->id,
                'carrier_rate' => 145000,
                'carrier_rate_currency' => 'RUB',
                'payment_form' => 'безнал НДС',
                'available_date' => '2026-07-05',
                'conditions' => 'Готов податься утром.',
            ])
            ->assertRedirect();

        $offer = LoadBoardOffer::query()->firstOrFail();
        $this->assertSame($buyer->id, $offer->created_by);
        $this->assertSame('has_offers', $post->fresh()->status);

        $this->actingAs($seller)
            ->post(route('load-board.offers.select', ['post' => $post, 'offer' => $offer]))
            ->assertRedirect();

        $this->assertSame('selected', $offer->fresh()->status);
        $this->assertSame('seller_review', $post->fresh()->status);

        $this->actingAs($seller)
            ->post(route('load-board.offers.approve', ['post' => $post, 'offer' => $offer]))
            ->assertRedirect();

        $post->refresh();
        $offer->refresh();
        $order->refresh();
        $task->refresh();

        $this->assertSame('approved', $offer->status);
        $this->assertSame('closed', $post->status);
        $this->assertSame($offer->id, $post->accepted_offer_id);
        $this->assertSame($seller->id, $post->accepted_by);
        $this->assertSame($carrier->id, $order->carrier_id);
        $this->assertOrderCarrierRate($order->id, 145000.0);
        $this->assertSame($offer->id, $order->metadata['load_board_accepted_offer']['offer_id']);
        $this->assertSame('done', $task->status);
    }

    public function test_index_returns_paginated_posts_payload(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_index',
            'display_name' => 'Load board role index',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get(route('load-board.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LoadBoard/Index')
                ->has('posts.data')
                ->has('posts.meta')
                ->where('posts.meta.per_page', 50)
                ->has('activePostsCount'));
    }

    public function test_rows_endpoint_returns_next_page_for_infinite_scroll(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_rows',
            'display_name' => 'Load board role rows',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        for ($index = 1; $index <= 55; $index++) {
            LoadBoardPost::query()->create([
                'seller_id' => $user->id,
                'status' => 'new',
                'priority' => 'normal',
                'title' => "Груз {$index}",
                'published_at' => now(),
            ]);
        }

        $firstPage = $this->actingAs($user)
            ->getJson(route('load-board.rows', ['filter' => 'all', 'page' => 1]))
            ->assertOk()
            ->json();

        $this->assertCount(50, $firstPage['data']);
        $this->assertTrue($firstPage['meta']['has_more']);
        $this->assertSame(55, $firstPage['meta']['total']);

        $secondPage = $this->actingAs($user)
            ->getJson(route('load-board.rows', ['filter' => 'all', 'page' => 2]))
            ->assertOk()
            ->json();

        $this->assertCount(5, $secondPage['data']);
        $this->assertFalse($secondPage['meta']['has_more']);
    }

    public function test_store_offer_records_rate_observation_with_source(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_observation',
            'display_name' => 'Load board observation',
            'visibility_areas' => ['load_board'],
        ]);

        $buyer = User::factory()->create(['role_id' => $role->id]);
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'is_active' => true,
        ]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $buyer->id,
            'status' => 'in_work',
            'priority' => 'normal',
            'title' => 'Москва → Казань',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'cargo_weight' => 20,
            'customer_rate' => 180000,
            'customer_rate_currency' => 'RUB',
            'published_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->post(route('load-board.offers.store', $post), [
                'carrier_id' => $carrier->id,
                'carrier_rate' => 145000,
                'carrier_rate_currency' => 'RUB',
                'source' => 'ati_manual',
            ])
            ->assertRedirect();

        $offer = LoadBoardOffer::query()->firstOrFail();

        $this->assertDatabaseHas('load_board_rate_observations', [
            'load_board_post_id' => $post->id,
            'load_board_offer_id' => $offer->id,
            'carrier_rate' => 145000,
            'source' => 'ati_manual',
            'outcome' => 'open',
        ]);

        $row = $this->actingAs($buyer)
            ->getJson(route('load-board.rows', ['filter' => 'all', 'page' => 1]))
            ->assertOk()
            ->json('data.0');

        $this->assertSame(145000.0, (float) $row['offers_summary']['best_rate']);
        $this->assertSame('ATI (вручную)', $row['offers_summary']['sources_label']);
    }

    public function test_insights_endpoint_returns_corridor_statistics(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_insights',
            'display_name' => 'Load board insights',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'has_offers',
            'priority' => 'normal',
            'title' => 'Москва → Казань',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'cargo_weight' => 20,
            'customer_rate' => 200000,
            'customer_rate_currency' => 'RUB',
            'published_at' => now(),
        ]);

        LoadBoardRateObservation::query()->create([
            'load_board_post_id' => $post->id,
            'carrier_rate' => 150000,
            'carrier_rate_currency' => 'RUB',
            'corridor_key' => LoadBoardCorridorKey::forPost($post),
            'loading_location' => $post->loading_location,
            'unloading_location' => $post->unloading_location,
            'customer_rate' => 200000,
            'customer_rate_currency' => 'RUB',
            'margin_abs' => 50000,
            'margin_pct' => 25,
            'source' => 'internal_crm',
            'outcome' => 'open',
            'observed_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('load-board.insights', $post))
            ->assertOk()
            ->assertJsonPath('post_id', $post->id)
            ->assertJsonPath('insights.available', true)
            ->assertJsonPath('insights.sample_size', 1)
            ->assertJsonPath('insights.carrier_rate.min', 150000);
    }

    public function test_load_board_post_uses_order_owner_as_seller_and_creates_procurement_case(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id') || ! Schema::hasTable('procurement_cases')) {
            $this->markTestSkipped('order_owner_id or procurement_cases missing');
        }

        $role = Role::query()->create([
            'name' => 'load_board_role_owner',
            'display_name' => 'Load board owner',
            'visibility_areas' => ['load_board'],
        ]);

        $publisher = User::factory()->create(['role_id' => $role->id]);
        $owner = User::factory()->create(['role_id' => $role->id]);

        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент владелец',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'LB-OWNER-1',
            'manager_id' => $owner->id,
            'order_owner_id' => $owner->id,
            'dispatcher_id' => $publisher->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'is_active' => true,
        ]);

        $this->actingAs($publisher)
            ->post(route('load-board.store'), [
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'priority' => 'normal',
                'title' => 'Москва → СПб',
                'loading_location' => 'Москва',
                'unloading_location' => 'Санкт-Петербург',
                'loading_date' => '2026-07-10',
                'cargo_name' => 'ТНП',
                'ati_cargo_name' => 'ТНП',
                'cargo_weight' => 10,
                'customer_rate' => 120000,
                'customer_rate_currency' => 'RUB',
            ])
            ->assertRedirect(route('load-board.index'));

        $post = LoadBoardPost::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($post);
        $this->assertSame($owner->id, $post->seller_id);

        $this->assertDatabaseHas('procurement_cases', [
            'load_board_post_id' => $post->id,
            'order_id' => $order->id,
            'order_owner_id' => $owner->id,
            'dispatcher_id' => $publisher->id,
            'status' => 'new',
        ]);

        $this->assertIsArray($post->fresh()->procurementCase?->metadata['linked_orders'] ?? null);
    }

    public function test_procurement_case_link_attach_adds_secondary_order(): void
    {
        if (! Schema::hasTable('procurement_cases')) {
            $this->markTestSkipped('procurement_cases missing');
        }

        $role = Role::query()->create([
            'name' => 'load_board_role_links',
            'display_name' => 'Load board links',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент links',
            'is_active' => true,
        ]);

        $primaryOrder = Order::query()->create([
            'order_number' => 'LB-LINK-1',
            'manager_id' => $user->id,
            'order_owner_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'is_active' => true,
        ]);

        $secondaryOrder = Order::query()->create([
            'order_number' => 'LB-LINK-2',
            'manager_id' => $user->id,
            'order_owner_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'is_active' => true,
        ]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'customer_id' => $customer->id,
            'order_id' => $primaryOrder->id,
            'status' => 'new',
            'priority' => 'normal',
            'title' => 'Links test',
            'loading_location' => 'Москва',
            'unloading_location' => 'Тула',
            'published_at' => now(),
        ]);

        app(ProcurementCaseSyncService::class)->ensureForPost($post->fresh());

        $this->actingAs($user)
            ->patch(route('load-board.procurement-case.links.attach', $post), [
                'type' => 'order',
                'id' => $secondaryOrder->id,
            ])
            ->assertRedirect();

        $metadata = $post->fresh()->procurementCase?->metadata;
        $this->assertIsArray($metadata);
        $linkedIds = collect($metadata['linked_orders'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($primaryOrder->id, $linkedIds);
        $this->assertContains($secondaryOrder->id, $linkedIds);
    }

    public function test_rows_payload_includes_procurement_case(): void
    {
        if (! Schema::hasTable('procurement_cases')) {
            $this->markTestSkipped('procurement_cases missing');
        }

        $role = Role::query()->create([
            'name' => 'load_board_role_present',
            'display_name' => 'Load board present',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id, 'name' => 'Seller Present']);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'new',
            'priority' => 'normal',
            'title' => 'Present case',
            'loading_location' => 'A',
            'unloading_location' => 'B',
            'published_at' => now(),
        ]);

        app(ProcurementCaseSyncService::class)->ensureForPost($post->fresh());

        $row = $this->actingAs($user)
            ->getJson(route('load-board.rows', ['filter' => 'all']))
            ->assertOk()
            ->json('data.0');

        $this->assertSame($post->id, $row['id']);
        $this->assertIsArray($row['procurement_case']);
        $this->assertSame($post->fresh()->procurementCase?->id, $row['procurement_case']['id']);
    }

    public function test_case_show_page_includes_advisor_and_carrier_pool(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_show',
            'display_name' => 'Load board show',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'new',
            'priority' => 'normal',
            'title' => 'Show case',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('load-board.cases.show', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LoadBoard/Show')
                ->has('post')
                ->has('advisor.ranked_offers')
                ->has('advisor.risk_level')
                ->has('carrierPool.entries'));
    }

    public function test_advisor_endpoint_returns_ranking_and_pool(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_advisor',
            'display_name' => 'Load board advisor api',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'API Carrier',
            'is_active' => true,
        ]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'has_offers',
            'priority' => 'normal',
            'title' => 'Advisor API',
            'loading_location' => 'СПб',
            'unloading_location' => 'Москва',
            'customer_rate' => 100000,
            'published_at' => now(),
        ]);

        LoadBoardOffer::query()->create([
            'load_board_post_id' => $post->id,
            'carrier_id' => $carrier->id,
            'created_by' => $user->id,
            'status' => 'proposed',
            'source' => 'email',
            'carrier_rate' => 85000,
            'carrier_rate_currency' => 'RUB',
        ]);

        $payload = $this->actingAs($user)
            ->getJson(route('load-board.advisor', $post))
            ->assertOk()
            ->json('advisor');

        $this->assertIsArray($payload['ranked_offers']);
        $this->assertNotEmpty($payload['ranked_offers']);
        $this->assertSame(1, $payload['carrier_pool']['total']);
    }

    public function test_user_can_add_and_remove_carrier_pool_candidate(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_pool_ui',
            'display_name' => 'Load board pool ui',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'in_work',
            'priority' => 'normal',
            'title' => 'Pool candidate',
            'loading_location' => 'Москва',
            'unloading_location' => 'Тула',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('load-board.carrier-pool.candidates.store', $post), [
                'carrier_name' => 'ИП Звонок',
                'source' => 'phone',
                'carrier_rate' => 90000,
                'carrier_contact' => '+7 900 000-00-00',
                'comment' => 'Перезвонить',
            ])
            ->assertRedirect();

        $metadata = $post->fresh()->metadata;
        $this->assertIsArray($metadata);
        $this->assertCount(1, $metadata['carrier_pool_candidates'] ?? []);
        $candidateId = (string) ($metadata['carrier_pool_candidates'][0]['id'] ?? '');
        $this->assertNotSame('', $candidateId);

        $pool = app(LoadBoardCarrierPoolService::class)->forPost($post->fresh());
        $this->assertSame(1, $pool['total']);
        $this->assertSame('candidate', $pool['entries'][0]['kind']);

        $this->actingAs($user)
            ->delete(route('load-board.carrier-pool.candidates.destroy', [
                'post' => $post,
                'candidate' => $candidateId,
            ]))
            ->assertRedirect();

        $this->assertSame([], $post->fresh()->metadata['carrier_pool_candidates'] ?? []);
    }

    public function test_duplicate_carrier_pool_candidate_is_rejected(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_role_pool_dup',
            'display_name' => 'Load board pool dup',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Dup Carrier',
            'is_active' => true,
        ]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'in_work',
            'priority' => 'normal',
            'title' => 'Dup pool',
            'loading_location' => 'СПб',
            'unloading_location' => 'Москва',
            'published_at' => now(),
            'metadata' => [
                'carrier_pool_candidates' => [[
                    'id' => 'cand-1',
                    'carrier_id' => $carrier->id,
                    'carrier_name' => 'Dup Carrier',
                    'source' => 'phone',
                    'carrier_rate' => 80000,
                    'carrier_rate_currency' => 'RUB',
                ]],
            ],
        ]);

        $this->actingAs($user)
            ->from(route('load-board.cases.show', $post))
            ->post(route('load-board.carrier-pool.candidates.store', $post), [
                'carrier_id' => $carrier->id,
                'source' => 'phone',
                'carrier_rate' => 79000,
            ])
            ->assertRedirect(route('load-board.cases.show', $post))
            ->assertSessionHasErrors('carrier_id');
    }
}
