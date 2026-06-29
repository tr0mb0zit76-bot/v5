<?php

namespace Tests\Unit\Services\Leads;

use App\Models\Lead;
use App\Services\Leads\LeadRoutePriceBenchmarkService;
use Tests\TestCase;

class LeadRoutePriceBenchmarkServiceTest extends TestCase
{
    public function test_returns_unavailable_when_route_is_empty(): void
    {
        $lead = Lead::factory()->create([
            'loading_location' => null,
            'unloading_location' => null,
        ]);

        $result = app(LeadRoutePriceBenchmarkService::class)->benchmarkForLead($lead);

        $this->assertNotNull($result);
        $this->assertFalse($result['available']);
        $this->assertSame(0, $result['sample_size']);
    }
}
