<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OwnFleetCostNormsAccessTest extends TestCase
{
    public function test_own_fleet_role_can_edit_norms_orders_cannot(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users') || ! Schema::hasTable('own_fleet_cost_norms')) {
            $this->markTestSkipped('Нужны таблицы roles, users и own_fleet_cost_norms.');
        }

        $allowedRole = Role::query()->firstOrCreate(
            ['name' => 'fleet_norms_tester'],
            [
                'display_name' => 'Fleet norms tester',
                'visibility_areas' => ['own_fleet'],
            ],
        );
        $allowedRole->update(['visibility_areas' => ['own_fleet']]);

        $deniedRole = Role::query()->firstOrCreate(
            ['name' => 'fleet_norms_denied'],
            [
                'display_name' => 'Fleet norms denied',
                'visibility_areas' => ['modules_how_much_costs', 'orders'],
            ],
        );
        $deniedRole->update(['visibility_areas' => ['modules_how_much_costs', 'orders']]);

        $allowedUser = User::factory()->create(['role_id' => $allowedRole->id]);
        $deniedUser = User::factory()->create(['role_id' => $deniedRole->id]);

        $this->actingAs($allowedUser)
            ->get(route('fleet.cost-norms.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Fleet/CostNorms')->has('norms'));

        $this->actingAs($deniedUser)
            ->get(route('fleet.cost-norms.edit'))
            ->assertForbidden();

        $payload = [
            'cn' => [
                'fuel_price_rub_per_liter' => 70,
                'fuel_consumption_l_per_100km' => 12,
                'driver_rub_per_km' => 5,
                'other_rub_per_km' => 1,
            ],
            'ru' => [
                'fuel_price_rub_per_liter' => 65,
                'fuel_consumption_l_per_100km' => 12,
                'driver_rub_per_km' => 7,
                'other_rub_per_km' => 2,
            ],
            'depreciation_rub_per_km' => 3,
            'margin_percent' => 12,
            'margin_absolute_rub' => 3000,
        ];

        $this->actingAs($allowedUser)
            ->put(route('fleet.cost-norms.update'), $payload)
            ->assertRedirect();

        $this->actingAs($deniedUser)
            ->put(route('fleet.cost-norms.update'), $payload)
            ->assertForbidden();
    }
}
