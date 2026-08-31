<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\OwnFleetCostNormsService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HowMuchCostsModuleTest extends TestCase
{
    public function test_module_requires_visibility_area_and_calculates(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users') || ! Schema::hasTable('own_fleet_cost_norms')) {
            $this->markTestSkipped('Нужны таблицы roles, users и own_fleet_cost_norms.');
        }

        app(OwnFleetCostNormsService::class)->update([
            'cn' => [
                'fuel_price_rub_per_liter' => 60,
                'fuel_consumption_l_per_100km' => 10,
                'driver_rub_per_km' => 0,
                'other_rub_per_km' => 0,
            ],
            'ru' => [
                'fuel_price_rub_per_liter' => 60,
                'fuel_consumption_l_per_100km' => 10,
                'driver_rub_per_km' => 0,
                'other_rub_per_km' => 0,
            ],
            'depreciation_rub_per_km' => 0,
            'margin_percent' => 10,
            'margin_absolute_rub' => 0,
        ]);

        $allowedRole = Role::query()->firstOrCreate(
            ['name' => 'how_much_costs_tester'],
            [
                'display_name' => 'How much costs tester',
                'visibility_areas' => ['modules_how_much_costs'],
            ],
        );
        $allowedRole->update(['visibility_areas' => ['modules_how_much_costs']]);

        $deniedRole = Role::query()->firstOrCreate(
            ['name' => 'how_much_costs_denied'],
            [
                'display_name' => 'How much costs denied',
                'visibility_areas' => ['orders'],
            ],
        );
        $deniedRole->update(['visibility_areas' => ['orders']]);

        $allowedUser = User::factory()->create(['role_id' => $allowedRole->id]);
        $deniedUser = User::factory()->create(['role_id' => $deniedRole->id]);

        $this->actingAs($allowedUser)
            ->get(route('modules.how-much-costs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/HowMuchCosts')
                ->where('normsConfigured', true)
                ->has('norms.cn.fuel_cost_rub_per_km'));

        $this->actingAs($deniedUser)
            ->get(route('modules.how-much-costs.index'))
            ->assertForbidden();

        $this->actingAs($allowedUser)
            ->postJson(route('modules.how-much-costs.calculate'), [
                'km_to_border' => 100,
                'km_from_border' => 100,
            ])
            ->assertOk()
            ->assertJsonPath('totals.cost_price', 1200)
            ->assertJsonPath('totals.customer_price', 1320);

        $this->actingAs($deniedUser)
            ->postJson(route('modules.how-much-costs.calculate'), [
                'km_to_border' => 100,
                'km_from_border' => 100,
            ])
            ->assertForbidden();
    }
}
