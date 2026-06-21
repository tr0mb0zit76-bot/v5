<?php

namespace Tests\Unit;

use App\Support\ContractorIdentity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorIdentityTest extends TestCase
{
    #[Test]
    public function it_normalizes_inn_to_digits_only(): void
    {
        $this->assertSame('7707083893', ContractorIdentity::normalizeInn('77 07 083893'));
        $this->assertSame('123456789012', ContractorIdentity::normalizeInn(' 123-456-789-012 '));
        $this->assertNull(ContractorIdentity::normalizeInn(''));
        $this->assertNull(ContractorIdentity::normalizeInn('   '));
        $this->assertNull(ContractorIdentity::normalizeInn(null));
    }

    #[Test]
    public function it_trims_company_name(): void
    {
        $this->assertSame('ООО Ромашка', ContractorIdentity::normalizeName('  ООО Ромашка  '));
        $this->assertSame('', ContractorIdentity::normalizeName('   '));
    }
}
