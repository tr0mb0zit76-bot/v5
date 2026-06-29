<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Models\User;
use App\Services\OrderPortalInviteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCarrierPortalTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database is not available.');
        }

        parent::setUp();

        if (! Schema::hasTable('orders')) {
            $this->markTestSkipped('Orders table is not available.');
        }
    }

    public function test_carrier_portal_show_and_submit_updates_order_performers(): void
    {
        if (! Schema::hasTable('order_portal_invites')) {
            $this->markTestSkipped('order_portal_invites migration is not applied.');
        }

        $user = User::factory()->create();
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Тестовый перевозчик',
            'is_active' => true,
        ]);
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'carrier_id' => $carrier->id,
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'carrier_mode' => 'single',
                    'contractor_id' => $carrier->id,
                    'contractor_name' => $carrier->name,
                    'split_carriers' => [],
                ],
            ],
        ]);

        $token = 'test-portal-token-'.uniqid('', true);
        $invite = OrderPortalInvite::query()->create([
            'order_id' => $order->id,
            'contractor_id' => $carrier->id,
            'stage' => 'leg_1',
            'carrier_slot' => 1,
            'purpose' => OrderPortalInvite::PURPOSE_CARRIER_FLEET,
            'token_hash' => app(OrderPortalInviteService::class)->hashToken($token),
            'created_by' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('portal.carrier.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/CarrierFleet')
                ->where('status', 'open')
                ->where('carrier.name', $carrier->name));

        $this->post(route('portal.carrier.store', ['token' => $token]), [
            'tractor_plate' => 'A123BC77',
            'trailer_plate' => 'BB4567 77',
            'driver_full_name' => 'Иванов Иван Иванович',
            'driver_phone' => '+79001234567',
        ])->assertRedirect(route('portal.carrier.show', ['token' => $token]));

        $invite->refresh();
        $order->refresh();

        $this->assertNotNull($invite->used_at);
        $this->assertSame('Иванов Иван Иванович', data_get($invite->submitted_payload, 'driver_full_name'));

        $performer = collect($order->performers)->first();
        $this->assertIsArray($performer);
        $this->assertNotNull($performer['fleet_vehicle_id'] ?? null);
        $this->assertNotNull($performer['fleet_driver_id'] ?? null);
        $this->assertSame('Иванов Иван Иванович', data_get($performer, 'carrier_portal_submission.driver_full_name'));
    }

    public function test_store_carrier_invite_accepts_localized_stage_name(): void
    {
        if (! Schema::hasTable('order_portal_invites')) {
            $this->markTestSkipped('order_portal_invites migration is not applied.');
        }

        $user = User::factory()->create();
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Локализованный перевозчик',
            'is_active' => true,
        ]);
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'carrier_id' => $carrier->id,
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'carrier_mode' => 'single',
                    'contractor_id' => $carrier->id,
                    'contractor_name' => $carrier->name,
                    'split_carriers' => [],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(
            route('orders.portal-invites.carrier.store', $order),
            [
                'contractor_id' => $carrier->id,
                'stage' => 'Плечо 1',
                'carrier_slot' => 1,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonStructure(['url', 'expires_at', 'invite_id']);
    }
}
