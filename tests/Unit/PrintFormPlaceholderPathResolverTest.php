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

    #[Test]
    public function it_maps_stern_customer_form_placeholders(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('own_company.signer_authority_basis', $resolver->resolve('lp_osnovanie', [], 'order', 'customer'));
        $this->assertSame('customer.signer_authority_basis', $resolver->resolve('cp_osnovanie', [], 'order', 'customer'));
        $this->assertSame('route.loading_first_address', $resolver->resolve('adres_pogruzki', [], 'order', 'customer'));
        $this->assertSame('route.unloading_first_address', $resolver->resolve('adres_vygruzki', [], 'order', 'customer'));
        $this->assertSame('cargo_sender.contact_phone', $resolver->resolve('kontankt_pogruzka', [], 'order', 'customer'));
        $this->assertSame('cargo_sender.name', $resolver->resolve('gruzootpravitel', [], 'order', 'customer'));
        $this->assertSame('cargo.line_1_name', $resolver->resolve('cargo_row_name', [], 'order', 'customer'));
        $this->assertSame('order.customer_rate_with_currency', $resolver->resolve('stoimost', [], 'order', 'customer'));
        $this->assertSame('order.customer_payment_term', $resolver->resolve('usloviya_oplaty', [], 'order', 'customer'));
        $this->assertSame('order.special_notes', $resolver->resolve('primechanya', [], 'order', 'customer'));
        $this->assertSame('own_company.legal_address', $resolver->resolve('lp_yur_address', [], 'order', 'customer'));
        $this->assertSame('customer.postal_address', $resolver->resolve('cp_pocht_address', [], 'order', 'customer'));
    }

    #[Test]
    public function it_maps_dispatcher_placeholders(): void
    {
        $resolver = new PrintFormPlaceholderPathResolver;

        $this->assertSame('dispatcher.name', $resolver->resolve('dispatcher', [], 'order', 'customer'));
        $this->assertSame('dispatcher.phone', $resolver->resolve('dispatcher_tel', [], 'order', 'customer'));
        $this->assertSame('dispatcher.email', $resolver->resolve('dispatcher_email', [], 'order', 'customer'));
        $this->assertSame('dispatcher.name', $resolver->resolve('dispetcher', [], 'order', 'customer'));
        $this->assertSame('dispatcher.phone', $resolver->resolve('lp_dispatcher_tel', [], 'order', 'customer'));
        $this->assertSame('dispatcher.email', $resolver->resolve('lp_dispetcher_email', [], 'order', 'customer'));
    }
}
