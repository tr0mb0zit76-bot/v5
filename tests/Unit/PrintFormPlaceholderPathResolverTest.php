<?php

namespace Tests\Unit;

use App\Support\PrintFormPlaceholderPathResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrintFormPlaceholderPathResolverTest extends TestCase
{
    #[Test]
    public function it_maps_dp_kpp_to_own_company_for_customer_party_templates(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $path = $resolver->resolve('dp_KPP', [], 'order', 'customer');

        $this->assertSame('own_company.kpp', $path);
    }
}
