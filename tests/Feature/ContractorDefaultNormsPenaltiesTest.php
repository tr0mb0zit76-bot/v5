<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Support\PartyNormsPenalties;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorDefaultNormsPenaltiesTest extends TestCase
{
    #[Test]
    public function it_stores_default_customer_norms_penalties_on_contractor(): void
    {
        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Тест',
            'inn' => '7707083893',
            'is_active' => true,
            'default_customer_norms_penalties' => PartyNormsPenalties::normalizeForStorage([
                'miss_amount' => 5000,
                'miss_currency' => 'RUB',
                'norm_loading_hours' => 24,
                'penalty_terms' => 'Пеня 0,1%',
            ]),
        ]);

        $contractor->refresh();

        $this->assertIsArray($contractor->default_customer_norms_penalties);
        $this->assertEquals(5000, $contractor->default_customer_norms_penalties['miss_amount']);
        $this->assertEquals(24, $contractor->default_customer_norms_penalties['norm_loading_hours']);
        $this->assertSame('Пеня 0,1%', $contractor->default_customer_norms_penalties['penalty_terms']);
    }
}
