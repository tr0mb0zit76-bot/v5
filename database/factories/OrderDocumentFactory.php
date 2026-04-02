<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDocument>
 */
class OrderDocumentFactory extends Factory
{
    protected $model = OrderDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => 'request',
            'status' => 'draft',
        ];
    }
}
