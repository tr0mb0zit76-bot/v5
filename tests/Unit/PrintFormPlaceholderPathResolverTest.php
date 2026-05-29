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

    public function test_order_legacy_gruz_maps_to_cargo_line_summary(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('cargo.line_1_summary', $resolver->resolve('gruz_1', [], 'order'));
        $this->assertSame('cargo.line_5_summary', $resolver->resolve('gruz_5', [], 'order'));
    }

    public function test_order_legacy_cargo_name_maps_to_cargo_line_name(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('cargo.line_1_name', $resolver->resolve('cargo_name1', [], 'order'));
        $this->assertSame('cargo.line_3_name', $resolver->resolve('cargo_name3', [], 'order'));
    }

    public function test_ved_prefix_lp_maps_to_own_company(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('own_company.inn', $resolver->resolve('lp_inn', [], 'order'));
        $this->assertSame('own_company.name', $resolver->resolve('lp_nazv', [], 'order'));
        $this->assertSame('manager.name', $resolver->resolve('lp_manager', [], 'order'));
    }

    public function test_ved_prefix_cp_maps_to_customer(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('customer.inn', $resolver->resolve('cp_inn', [], 'order'));
        $this->assertSame('order.customer_rate_with_currency', $resolver->resolve('cp_stavka', [], 'order'));
        $this->assertSame('contacts.customer_name', $resolver->resolve('cp_manager', [], 'order'));
    }

    public function test_ved_prefix_dp_maps_to_carrier(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('carrier.inn', $resolver->resolve('dp_INN', [], 'order'));
        $this->assertSame('order.carrier_rate_with_currency', $resolver->resolve('dp_stavka', [], 'order'));
        $this->assertSame('contacts.carrier_phone', $resolver->resolve('dp_kontakt_tel', [], 'order'));
    }

    public function test_cyrillic_cp_prefix_is_normalized(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('customer.inn', $resolver->resolve('сp_inn', [], 'order'));
    }

    public function test_cargo_declared_sum_maps_to_order_field(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('order.cargo_declared_sum', $resolver->resolve('cargo_declared_sum', [], 'order'));
    }
}
