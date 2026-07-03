<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\Order;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
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
        $this->assertSame('145000.00', (string) $order->carrier_rate);
        $this->assertSame($offer->id, $order->metadata['load_board_accepted_offer']['offer_id']);
        $this->assertSame('done', $task->status);
    }
}
