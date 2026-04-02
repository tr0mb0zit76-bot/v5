<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderLeg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLeg>
 */
class OrderLegFactory extends Factory
{
    protected $model = OrderLeg::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => [],
        ];
    }
}
