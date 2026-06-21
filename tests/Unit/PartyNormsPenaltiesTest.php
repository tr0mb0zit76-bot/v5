<?php

namespace Tests\Unit;

use App\Support\PartyNormsPenalties;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartyNormsPenaltiesTest extends TestCase
{
    #[Test]
    public function it_normalizes_and_strips_empty_norms_payload(): void
    {
        $normalized = PartyNormsPenalties::normalizeForStorage([
            'miss_amount' => '1000',
            'miss_currency' => 'rub',
            'penalty_terms' => '  0,1% в день  ',
        ]);

        $this->assertNotNull($normalized);
        $this->assertSame(1000.0, $normalized['miss_amount']);
        $this->assertSame('RUB', $normalized['miss_currency']);
        $this->assertSame('0,1% в день', $normalized['penalty_terms']);
    }

    #[Test]
    public function it_returns_null_when_norms_payload_has_no_meaningful_values(): void
    {
        $this->assertNull(PartyNormsPenalties::normalizeForStorage([
            'miss_currency' => 'RUB',
            'penalty_terms' => '',
        ]));
    }
}
