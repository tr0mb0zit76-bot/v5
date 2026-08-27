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

    #[Test]
    public function it_normalizes_carrier_norms_by_leg_and_keeps_stage(): void
    {
        $rows = PartyNormsPenalties::normalizeCarrierNormsByLegForStorage([
            [
                'stage' => 'leg_1',
                'miss_amount' => 2000,
                'miss_currency' => 'rub',
            ],
            [
                'stage' => 'leg_2',
                'miss_currency' => 'RUB',
            ],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('leg_1', $rows[0]['stage']);
        $this->assertSame(2000.0, $rows[0]['miss_amount']);
    }

    #[Test]
    public function it_backfills_missing_carrier_stage_from_performers(): void
    {
        $rows = PartyNormsPenalties::normalizeCarrierNormsByLegForStorage(
            [
                [
                    'miss_amount' => 1000,
                    'miss_currency' => 'RUB',
                ],
            ],
            [
                ['stage' => 'leg_1', 'contractor_id' => 1],
            ],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('leg_1', $rows[0]['stage']);
        $this->assertSame(1000.0, $rows[0]['miss_amount']);
    }

    #[Test]
    public function it_merges_incoming_norms_over_preserved_financial_term(): void
    {
        $merged = PartyNormsPenalties::mergeIncomingNormsIntoFinancialTerm(
            [
                'client_norms_penalties' => ['miss_amount' => 500],
                'carrier_norms_by_leg' => [['stage' => 'leg_1', 'miss_amount' => 100]],
            ],
            [
                'client_price' => 120000,
                'client_norms_penalties' => ['miss_amount' => 1],
            ],
        );

        $this->assertSame(120000, $merged['client_price']);
        $this->assertSame(500, $merged['client_norms_penalties']['miss_amount']);
        $this->assertSame('leg_1', $merged['carrier_norms_by_leg'][0]['stage']);
    }
}
