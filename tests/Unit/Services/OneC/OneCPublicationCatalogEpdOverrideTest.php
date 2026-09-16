<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\User;
use App\Services\OneC\OneCEpdStubMapper;
use App\Services\OneC\OneCPublicationCatalog;
use Tests\TestCase;

class OneCPublicationCatalogEpdOverrideTest extends TestCase
{
    public function test_sandbox_is_excluded_from_sync_catalog_but_resolvable_by_get(): void
    {
        config([
            'one_c.publications.sandbox.base_url' => 'https://avtoalyns.case-it.ru/AvtoAl_test2_34QG7659eH',
            'one_c.publications.sandbox.enabled' => true,
            'one_c.publications.sandbox.include_in_sync' => false,
            'one_c.publications.sandbox.organization_inn' => '',
        ]);

        $catalog = app(OneCPublicationCatalog::class);

        foreach ($catalog->all() as $pub) {
            $this->assertNotSame(OneCPublicationCatalog::CODE_SANDBOX, $pub['code']);
        }

        $sandbox = $catalog->get(OneCPublicationCatalog::CODE_SANDBOX);
        $this->assertSame(OneCPublicationCatalog::CODE_SANDBOX, $sandbox['code']);
        $this->assertStringContainsString('AvtoAl_test2', $sandbox['base_url']);
        $this->assertFalse($sandbox['include_in_sync']);
    }

    public function test_for_epd_order_uses_user_override(): void
    {
        config([
            'one_c.publications.sandbox.base_url' => 'https://avtoalyns.case-it.ru/AvtoAl_test2_34QG7659eH',
            'one_c.publications.sandbox.enabled' => true,
            'one_c.publications.sandbox.include_in_sync' => false,
            'one_c.publications.autalliance.base_url' => 'https://avtoalyns-crm.case-it.ru/Avtoalians_prod',
            'one_c.publications.autalliance.enabled' => true,
            'one_c.default_publication' => 'autalliance',
        ]);

        $order = new Order(['order_number' => 'X']);
        $order->id = 1;
        $order->setRelation('ownCompany', null);

        $actor = new User([
            'email' => 'test_2@avtoaliyans.ru',
            'one_c_epd_publication_override' => OneCPublicationCatalog::CODE_SANDBOX,
        ]);

        $catalog = app(OneCPublicationCatalog::class);
        $this->assertSame('autalliance', $catalog->forOrder($order)['code']);
        $this->assertSame('sandbox', $catalog->forEpdOrder($order, $actor)['code']);
        $this->assertSame('autalliance', $catalog->forEpdOrder($order, null)['code']);
    }

    public function test_mapper_routes_etrn_to_sandbox_for_override_user(): void
    {
        config([
            'one_c.publications.sandbox.base_url' => 'https://avtoalyns.case-it.ru/AvtoAl_test2_34QG7659eH',
            'one_c.publications.sandbox.enabled' => true,
            'one_c.publications.sandbox.organization_ref' => 'sandbox-org-ref',
            'one_c.publications.sandbox.include_in_sync' => false,
            'one_c.publications.autalliance.base_url' => 'https://avtoalyns-crm.case-it.ru/Avtoalians_prod',
            'one_c.publications.autalliance.enabled' => true,
            'one_c.default_publication' => 'autalliance',
        ]);

        $client = new Contractor([
            'name' => 'ООО Клиент',
            'inn' => '2312178145',
            'kpp' => '231201001',
        ]);
        $order = new Order([
            'order_number' => 'EPD-SANDBOX',
            'order_date' => '2026-09-16',
        ]);
        $order->id = 777;
        $order->setRelation('client', $client);
        $order->setRelation('carrier', null);
        $order->setRelation('ownCompany', null);
        $order->setRelation('legs', collect());
        $order->setRelation('routePoints', collect());
        $order->setRelation('cargoItems', collect());

        $actor = new User([
            'one_c_epd_publication_override' => OneCPublicationCatalog::CODE_SANDBOX,
        ]);

        $payload = app(OneCEpdStubMapper::class)->map($order, OrderOneCDocument::TYPE_ETRN, $actor);

        $this->assertSame('sandbox', $payload['publication_code']);
        $this->assertStringContainsString('AvtoAl_test2', (string) $payload['base_url']);
        $this->assertSame('sandbox-org-ref', $payload['organization_ref']);
    }
}
