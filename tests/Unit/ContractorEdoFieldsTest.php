<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Support\EdoProviderDictionary;
use App\Support\PrintFormPlaceholderPathResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorEdoFieldsTest extends TestCase
{
    #[Test]
    public function edo_provider_dictionary_returns_label_for_known_code(): void
    {
        $this->assertSame('Контур.Диадок', EdoProviderDictionary::label('diadoc'));
        $this->assertNull(EdoProviderDictionary::label(null));
    }

    #[Test]
    public function contractor_edo_print_payload_uses_provider_label(): void
    {
        $contractor = new Contractor([
            'edo_provider' => 'sbis',
            'edo_number' => '2BM-12345',
        ]);

        $this->assertSame([
            'edo_provider' => 'СБИС',
            'edo_number' => '2BM-12345',
        ], $contractor->edoPrintPayload());
    }

    #[Test]
    public function legacy_edo_placeholders_map_to_customer_and_carrier(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'customer.edo_provider',
            $resolver->resolve('provayder_edo', [], 'order', 'customer'),
        );
        $this->assertSame(
            'carrier.edo_number',
            $resolver->resolve('nomer_edo', [], 'order', 'carrier'),
        );
    }
}
