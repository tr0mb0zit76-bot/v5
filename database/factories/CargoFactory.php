<?php

namespace Database\Factories;

use App\Models\Cargo;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cargo>
 */
class CargoFactory extends Factory
{
    protected $model = Cargo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'weight' => fake()->randomFloat(2, 10, 1000),
            'volume' => fake()->randomFloat(2, 1, 100),
            'cargo_type' => 'general',
            'is_hazardous' => false,
        ];
    }
}
