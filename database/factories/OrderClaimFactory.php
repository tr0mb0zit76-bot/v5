<?php

namespace Database\Factories;

use App\Enums\OrderClaimParty;
use App\Enums\OrderClaimStatus;
use App\Enums\OrderClaimType;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderClaim>
 */
class OrderClaimFactory extends Factory
{
    protected $model = OrderClaim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'contractor_id' => null,
            'number' => 'CL-'.fake()->unique()->numerify('######-###'),
            'party' => OrderClaimParty::Customer,
            'type' => OrderClaimType::Late,
            'status' => OrderClaimStatus::Open,
            'title' => 'Срыв срока выгрузки',
            'description' => 'Клиент фиксирует простой на выгрузке.',
            'amount_risk' => 15000,
            'currency' => 'RUB',
            'responsible_id' => User::factory(),
            'created_by' => User::factory(),
            'due_at' => now()->addDays(7),
            'resolved_at' => null,
            'resolution_note' => null,
        ];
    }
}
