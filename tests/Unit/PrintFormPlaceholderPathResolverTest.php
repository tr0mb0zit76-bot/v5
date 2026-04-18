<?php

namespace Tests\Unit;

use App\Support\PrintFormPlaceholderPathResolver;
use PHPUnit\Framework\TestCase;

class PrintFormPlaceholderPathResolverTest extends TestCase
{
    public function test_order_legacy_maps_cyrillic_placeholder(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'order.order_number',
            $resolver->resolve('nomer_zayavki', [], 'order')
        );
    }

    public function test_order_explicit_overrides_legacy(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'order.waybill_number',
            $resolver->resolve('nomer_zayavki', ['nomer_zayavki' => 'order.waybill_number'], 'order')
        );
    }

    public function test_order_unknown_placeholder_falls_back_to_self(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'custom.field',
            $resolver->resolve('custom.field', [], 'order')
        );
    }

    public function test_lead_uses_placeholder_as_path_when_unmapped(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'lead.id',
            $resolver->resolve('lead.id', [], 'lead')
        );
    }

    public function test_effective_mapping_builds_array(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $effective = $resolver->effectiveVariableMapping(
            ['nomer_zayavki', 'lead_only'],
            [],
            'order'
        );

        $this->assertSame([
            'nomer_zayavki' => 'order.order_number',
            'lead_only' => 'lead_only',
        ], $effective);
    }
}
