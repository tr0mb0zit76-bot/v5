<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'LD-'.fake()->unique()->numerify('######'),
            'status' => fake()->randomElement(['new', 'qualification', 'calculation']),
            'source' => fake()->randomElement(['inbound', 'outbound', 'website']),
            'responsible_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'transport_type' => fake()->randomElement(['ftl', 'ltl', 'container']),
            'loading_location' => fake()->city(),
            'unloading_location' => fake()->city(),
            'planned_shipping_date' => fake()->dateTimeBetween('now', '+20 days'),
            'target_price' => fake()->randomFloat(2, 50000, 250000),
            'target_currency' => 'RUB',
            'calculated_cost' => fake()->randomFloat(2, 25000, 150000),
            'expected_margin' => fake()->randomFloat(2, 5000, 50000),
            'lead_qualification' => [
                'need' => fake()->sentence(3),
                'timeline' => '1 неделя',
            ],
        ];
    }
}
