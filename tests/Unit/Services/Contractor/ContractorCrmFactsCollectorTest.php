<?php

namespace Tests\Unit\Services\Contractor;

use App\Models\Contractor;
use App\Models\User;
use App\Services\Contractor\ContractorCrmFactsCollector;
use Tests\TestCase;

class ContractorCrmFactsCollectorTest extends TestCase
{
    public function test_collect_returns_zero_counts_for_new_contractor(): void
    {
        $user = User::factory()->create();
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Facts',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        $empty = app(ContractorCrmFactsCollector::class)->collect($contractor);
        $this->assertSame(0, $empty['relationships']['customer_orders_count']);
        $this->assertSame(0, $empty['relationships']['carrier_orders_count']);
        $this->assertSame([], $empty['recent_orders']);
        $this->assertSame('ООО Facts', $empty['identity']['name']);
    }
}
