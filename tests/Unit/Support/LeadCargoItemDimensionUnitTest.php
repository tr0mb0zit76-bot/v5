<?php

namespace Tests\Unit\Support;

use App\Support\LeadCargoItemPayloadNormalizer;
use Tests\TestCase;

class LeadCargoItemDimensionUnitTest extends TestCase
{
    public function test_to_database_converts_centimeter_dimensions_to_meters(): void
    {
        $payload = LeadCargoItemPayloadNormalizer::toDatabase([
            'name' => 'Короб',
            'cargo_type' => 'general',
            'length_value' => 250,
            'width_value' => 200,
            'height_value' => 150,
            'dimension_unit' => 'cm',
        ]);

        $this->assertSame(2.5, $payload['metadata']['length_m']);
        $this->assertSame(2.0, $payload['metadata']['width_m']);
        $this->assertSame(1.5, $payload['metadata']['height_m']);
        $this->assertSame(250.0, $payload['metadata']['length_value']);
        $this->assertSame('cm', $payload['metadata']['dimension_unit']);
        $this->assertSame(7.5, $payload['volume_m3']);
    }
}
