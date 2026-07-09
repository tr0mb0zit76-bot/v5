<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\Role;
use App\Models\User;
use App\Services\LoadBoard\LoadBoardAdvisorService;
use App\Services\LoadBoard\LoadBoardCarrierPoolService;
use Tests\TestCase;

class LoadBoardAdvisorServiceTest extends TestCase
{
    public function test_advisor_ranks_lower_carrier_rate_higher_and_flags_risk_without_offers(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_advisor',
            'display_name' => 'Load board advisor',
            'visibility_areas' => ['load_board'],
        ]);

        $seller = User::factory()->create(['role_id' => $role->id]);

        $carrierA = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Перевозчик A',
            'is_active' => true,
        ]);

        $carrierB = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Перевозчик B',
            'is_active' => true,
        ]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $seller->id,
            'status' => 'has_offers',
            'priority' => 'urgent',
            'title' => 'Москва → Казань',
            'loading_location' => 'Москва',
            'unloading_location' => 'Казань',
            'loading_date' => now()->addDay()->toDateString(),
            'customer_rate' => 200000,
            'customer_rate_currency' => 'RUB',
            'target_carrier_rate' => 160000,
            'published_at' => now(),
        ]);

        $expensive = LoadBoardOffer::query()->create([
            'load_board_post_id' => $post->id,
            'carrier_id' => $carrierA->id,
            'created_by' => $seller->id,
            'status' => 'proposed',
            'source' => 'internal_crm',
            'carrier_rate' => 170000,
            'carrier_rate_currency' => 'RUB',
        ]);

        $cheaper = LoadBoardOffer::query()->create([
            'load_board_post_id' => $post->id,
            'carrier_id' => $carrierB->id,
            'created_by' => $seller->id,
            'status' => 'proposed',
            'source' => 'phone',
            'carrier_rate' => 150000,
            'carrier_rate_currency' => 'RUB',
        ]);

        $post->load('offers.carrier');

        $advisor = app(LoadBoardAdvisorService::class)->advise($post, app(LoadBoardCarrierPoolService::class));

        $this->assertContains($advisor['risk_level'], ['low', 'medium', 'high']);
        $this->assertGreaterThan(0, $advisor['risk_score']);
        $this->assertNotEmpty($advisor['ranked_offers']);
        $this->assertSame($cheaper->id, $advisor['ranked_offers'][0]['offer_id']);
        $this->assertGreaterThan(
            $advisor['ranked_offers'][1]['score'],
            $advisor['ranked_offers'][0]['score'],
        );
        $this->assertSame(2, $advisor['carrier_pool']['total']);
    }

    public function test_carrier_pool_dedups_same_carrier_and_source(): void
    {
        $role = Role::query()->create([
            'name' => 'load_board_pool',
            'display_name' => 'Load board pool',
            'visibility_areas' => ['load_board'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Транс',
            'is_active' => true,
        ]);

        $post = LoadBoardPost::query()->create([
            'seller_id' => $user->id,
            'status' => 'has_offers',
            'priority' => 'normal',
            'title' => 'Пул',
            'loading_location' => 'СПб',
            'unloading_location' => 'Москва',
            'published_at' => now(),
        ]);

        LoadBoardOffer::query()->create([
            'load_board_post_id' => $post->id,
            'carrier_id' => $carrier->id,
            'created_by' => $user->id,
            'status' => 'proposed',
            'source' => 'internal_crm',
            'carrier_rate' => 120000,
            'carrier_rate_currency' => 'RUB',
        ]);

        LoadBoardOffer::query()->create([
            'load_board_post_id' => $post->id,
            'carrier_id' => $carrier->id,
            'created_by' => $user->id,
            'status' => 'proposed',
            'source' => 'internal_crm',
            'carrier_rate' => 115000,
            'carrier_rate_currency' => 'RUB',
        ]);

        $post->load('offers.carrier');

        $pool = app(LoadBoardCarrierPoolService::class)->forPost($post);

        $this->assertSame(1, $pool['total']);
        $this->assertSame(115000.0, (float) $pool['entries'][0]['carrier_rate']);
    }
}
