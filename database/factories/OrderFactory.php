<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'company_code' => 'ORD',
            'order_date' => fake()->date(),
            'status' => 'draft',
            'is_active' => true,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'performers' => [],
        ];
    }
}
