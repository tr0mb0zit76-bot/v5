<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadRateQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadRateQuote>
 */
class LeadRateQuoteFactory extends Factory
{
    protected $model = LeadRateQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'contractor_id' => null,
            'load_board_offer_id' => null,
            'created_by' => User::factory(),
            'carrier_name' => fake()->company(),
            'rate' => fake()->randomFloat(2, 10000, 250000),
            'currency' => 'RUB',
            'payment_form' => 'bank_transfer',
            'valid_until' => now()->addDays(3)->toDateString(),
            'source' => LeadRateQuote::SOURCE_MANUAL,
            'status' => LeadRateQuote::STATUS_RECEIVED,
            'comment' => null,
            'selected_at' => null,
        ];
    }
}
