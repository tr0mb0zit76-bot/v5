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

    #[Test]
    public function it_maps_gosnomer_ts_to_vehicle_number_and_ignores_identity_template_mapping(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'vehicle.number',
            $resolver->resolve('gosnomer_TS', [], 'order', 'customer'),
        );
        $this->assertSame(
            'vehicle.number',
            $resolver->resolve('gosnomer_TS', ['gosnomer_TS' => 'gosnomer_TS'], 'order', 'customer'),
        );
    }

    #[Test]
    public function it_maps_trailer_legacy_placeholders_to_vehicle_trailer_fields(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'vehicle.trailer_brand',
            $resolver->resolve('marka_priz', [], 'order', 'customer'),
        );
        $this->assertSame(
            'vehicle.trailer_plate',
            $resolver->resolve('gosnomer_priz', [], 'order', 'customer'),
        );
    }

    #[Test]
    public function it_maps_dp_podpisant_to_carrier_signer_position_when_fio_placeholder_exists(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'carrier.signer_position',
            $resolver->resolve('dp_podpisant', [], 'order', 'carrier'),
        );
        $this->assertSame(
            'carrier.signer_name_nominative',
            $resolver->resolve('dp_FIO_podpisant_im', [], 'order', 'carrier'),
        );
        $this->assertSame(
            'customer.signer_position',
            $resolver->resolve('cp_ceo_title', [], 'order', 'customer'),
        );
    }

    #[Test]
    public function it_maps_legacy_special_conditions_placeholders_to_route_fields(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame(
            'route.loading_special_conditions',
            $resolver->resolve('osobye_uslovia_pogruzki', [], 'order', 'customer'),
        );
        $this->assertSame(
            'route.unloading_special_conditions',
            $resolver->resolve('osobye_uslovia_vygruzki', [], 'order', 'carrier'),
        );
    }
}
