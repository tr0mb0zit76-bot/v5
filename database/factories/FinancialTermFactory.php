<?php

namespace Database\Factories;

use App\Models\FinancialTerm;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTerm>
 */
class FinancialTermFactory extends Factory
{
    protected $model = FinancialTerm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'client_price' => fake()->randomFloat(2, 10000, 200000),
            'client_currency' => 'RUB',
            'client_payment_terms' => '5 банковских дней',
            'contractors_costs' => [],
            'total_cost' => 0,
            'margin' => 0,
            'additional_costs' => [],
        ];
    }
}
