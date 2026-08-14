<?php

namespace Tests\Feature;

use App\Models\FleetTrip;
use App\Models\FleetTripCostLine;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FleetTripCostLinesUpdateTest extends TestCase
{
    public function test_update_saves_km_and_cost_lines_without_resending_order_identity(): void
    {
        if (! Schema::hasTable('fleet_trips') || ! Schema::hasTable('fleet_trip_cost_lines')) {
            $this->markTestSkipped('fleet_trips tables unavailable');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator', 'visibility_areas' => ['own_fleet', 'fleet_trips']],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $order = Order::factory()->create([
            'order_number' => 'АС-TEST-FLEET-'.uniqid(),
            'manager_id' => $user->id,
        ]);

        $trip = FleetTrip::query()->create([
            'order_id' => $order->id,
            'order_leg_stage' => 'leg_1',
            'status' => 'planned',
        ]);

        $this->actingAs($user)
            ->from(route('fleet.trips.show', $trip))
            ->patch(route('fleet.trips.update', $trip), [
                'status' => 'planned',
                'planned_km' => 550,
                'actual_km' => 550,
                'estimated_cost' => null,
                'cost_lines' => [
                    [
                        'cost_category' => 'fuel',
                        'amount' => 14932,
                        'currency' => 'RUB',
                        'comment' => '',
                    ],
                    [
                        'cost_category' => 'driver_salary',
                        'amount' => 6600,
                        'currency' => 'RUB',
                        'comment' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('fleet.trips.show', $trip));

        $trip->refresh();
        $this->assertSame(550, $trip->planned_km);
        $this->assertSame(550, $trip->actual_km);
        $this->assertSame(21532.0, (float) $trip->total_cost);

        $lines = FleetTripCostLine::query()
            ->where('fleet_trip_id', $trip->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $lines);
        $this->assertSame('fuel', $lines[0]->cost_category);
        $this->assertSame(14932.0, (float) $lines[0]->amount);
        $this->assertSame('driver_salary', $lines[1]->cost_category);
        $this->assertSame(6600.0, (float) $lines[1]->amount);
    }

    public function test_completed_trip_still_accepts_cost_lines_update(): void
    {
        if (! Schema::hasTable('fleet_trips') || ! Schema::hasTable('fleet_trip_cost_lines')) {
            $this->markTestSkipped('fleet_trips tables unavailable');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator', 'visibility_areas' => ['own_fleet', 'fleet_trips']],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $order = Order::factory()->create([
            'order_number' => 'АС-TEST-FLEET-DONE-'.uniqid(),
            'manager_id' => $user->id,
        ]);

        $trip = FleetTrip::query()->create([
            'order_id' => $order->id,
            'order_leg_stage' => 'leg_1',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('fleet.trips.update', $trip), [
                'planned_km' => 100,
                'actual_km' => 120,
                'cost_lines' => [
                    [
                        'cost_category' => 'fuel',
                        'amount' => 5000,
                        'currency' => 'RUB',
                    ],
                ],
            ])
            ->assertRedirect(route('fleet.trips.show', $trip));

        $trip->refresh();
        $this->assertSame(120, $trip->actual_km);
        $this->assertSame(5000.0, (float) $trip->total_cost);
        $this->assertDatabaseHas('fleet_trip_cost_lines', [
            'fleet_trip_id' => $trip->id,
            'cost_category' => 'fuel',
            'amount' => 5000,
        ]);
    }
}
