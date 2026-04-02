<?php

namespace Database\Factories;

use App\Models\OrderLeg;
use App\Models\RoutePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutePoint>
 */
class RoutePointFactory extends Factory
{
    protected $model = RoutePoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_leg_id' => OrderLeg::factory(),
            'type' => 'loading',
            'sequence' => 1,
            'address' => fake()->city().', '.fake()->streetAddress(),
            'normalized_data' => [],
            'planned_date' => fake()->date(),
        ];
    }
}
