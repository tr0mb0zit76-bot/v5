<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Models\Contractor;
use App\Models\Order;
use App\Services\OneC\OneCRealizationMapper;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OneCRealizationMapperTest extends TestCase
{
    public function test_maps_farmservice_order_with_teu_service_content(): void
    {
        config([
            'one_c.extra_attributes.order_id' => '',
            'one_c.extra_attributes.order_number' => '',
            'one_c.service_nomenclature.ref' => '9ec829b8-632e-11f1-8745-fa163ea037a3',
            'one_c.service_nomenclature.code' => '00-00000001',
            'one_c.organization_ref' => '19b37fca-5d84-11f1-8bf4-fa163ea037a3',
            'one_c.currency_ref' => '69e038af-320f-11f1-acc9-b69a48ddb3f4',
        ]);

        $client = new Contractor([
            'name' => 'ООО "ФАРМСЕРВИС"',
            'inn' => '2312178145',
            'kpp' => '231201001',
        ]);

        $order = new Order([
            'order_number' => 'ТЕСТ-1',
            'customer_rate' => '123500.00',
            'order_date' => '2026-08-10',
            'unloading_date' => '2026-08-12',
        ]);
        $order->id = 146;
        $order->setRelation('client', $client);
        $order->setRelation('legs', collect());

        $payload = app(OneCRealizationMapper::class)->map($order);

        $this->assertSame('РеализацияТоваровУслуг', $payload['document_type']);
        $this->assertSame('Услуги', $payload['operation_kind']);
        $this->assertSame(146, $payload['order_id']);
        $this->assertSame('ТЕСТ-1', $payload['order_number']);
        $this->assertSame('123500.00', $payload['amount']);
        $this->assertSame('2026-08-12', $payload['document_date']);
        $this->assertSame('2312178145', $payload['counterparty']['inn']);
        $this->assertSame('9ec829b8-632e-11f1-8745-fa163ea037a3', $payload['service_line']['nomenclature_ref']);
        $this->assertStringStartsWith('CRM:ТЕСТ-1:C1 ', $payload['service_line']['content']);
        $this->assertStringContainsString('Транспортно-экспедиционные услуги по Заявке № ТЕСТ-1 от 10.08.2026', $payload['service_line']['content']);
        $this->assertStringContainsString('публичной оферте', $payload['service_line']['content']);
        $this->assertSame([], $payload['extra_attributes']);
        $this->assertArrayNotHasKey('ДополнительныеРеквизиты', $payload['odata_stub']);
        $this->assertSame('19b37fca-5d84-11f1-8bf4-fa163ea037a3', $payload['odata_stub']['Организация_Key']);
        $this->assertSame('autalliance', $payload['publication_code']);
        $this->assertSame('69e038af-320f-11f1-acc9-b69a48ddb3f4', $payload['odata_stub']['ВалютаДокумента_Key']);
        $this->assertSame('CRM ТЕСТ-1 (id 146)', $payload['odata_stub']['Комментарий']);
        $this->assertSame(
            '9ec829b8-632e-11f1-8745-fa163ea037a3',
            $payload['odata_stub']['Услуги'][0]['Номенклатура_Key']
        );
        $this->assertSame('БезНДС', $payload['odata_stub']['Услуги'][0]['СтавкаНДС']);
        $this->assertSame(0.0, $payload['odata_stub']['Услуги'][0]['СуммаНДС']);
        $this->assertTrue($payload['odata_stub']['ДокументБезНДС']);
        $this->assertFalse($payload['odata_stub']['СуммаВключаетНДС']);
    }

    public function test_maps_own_company_profsfera_to_profsfera_publication(): void
    {
        config([
            'one_c.extra_attributes.order_id' => '',
            'one_c.extra_attributes.order_number' => '',
            'one_c.service_nomenclature.ref' => '9ec829b8-632e-11f1-8745-fa163ea037a3',
            'one_c.service_nomenclature.code' => '00-00000001',
            'one_c.organization_ref' => '19b37fca-5d84-11f1-8bf4-fa163ea037a3',
            'one_c.currency_ref' => '69e038af-320f-11f1-acc9-b69a48ddb3f4',
            'one_c.default_publication' => 'autalliance',
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'АА',
                    'base_url' => 'https://one-c.test/aa',
                    'organization_ref' => '19b37fca-5d84-11f1-8bf4-fa163ea037a3',
                    'organization_inn' => '6732110940',
                    'service_nomenclature_ref' => '9ec829b8-632e-11f1-8745-fa163ea037a3',
                    'service_nomenclature_code' => '00-00000001',
                    'enabled' => true,
                ],
                'profsfera' => [
                    'label' => 'Профсфера',
                    'base_url' => 'https://one-c.test/profsfera',
                    'organization_ref' => '68778110-58ca-11f1-8af0-fa163eafb81d',
                    'organization_inn' => '6321213940',
                    'service_nomenclature_ref' => 'af537684-63c4-11f1-8ae7-fa163eafb81d',
                    'service_nomenclature_code' => '00-00000002',
                    'enabled' => true,
                ],
            ],
        ]);

        $client = new Contractor([
            'name' => 'ИП Данилов',
            'inn' => '632111465177',
        ]);
        $own = new Contractor([
            'name' => 'ООО ПРОФСФЕРА',
            'inn' => '6321213940',
        ]);
        $own->id = 8;

        $order = new Order([
            'order_number' => 'ПС-СЭ-38',
            'customer_rate' => '25000.00',
            'customer_payment_form' => 'no_vat',
            'order_date' => '2026-08-11',
            'own_company_id' => 8,
        ]);
        $order->id = 150;
        $order->setRelation('client', $client);
        $order->setRelation('ownCompany', $own);
        $order->setRelation('legs', collect());

        $payload = app(OneCRealizationMapper::class)->map($order);

        $this->assertSame('profsfera', $payload['publication_code']);
        $this->assertSame('https://one-c.test/profsfera', $payload['base_url']);
        $this->assertSame('68778110-58ca-11f1-8af0-fa163eafb81d', $payload['organization_ref']);
        $this->assertSame('68778110-58ca-11f1-8af0-fa163eafb81d', $payload['odata_stub']['Организация_Key']);
        $this->assertSame('af537684-63c4-11f1-8ae7-fa163eafb81d', $payload['service_line']['nomenclature_ref']);
        $this->assertSame('00-00000002', $payload['service_line']['nomenclature_code']);
    }

    public function test_maps_customer_vat_22_into_service_line(): void
    {
        config([
            'one_c.extra_attributes.order_id' => '',
            'one_c.extra_attributes.order_number' => '',
            'one_c.service_nomenclature.ref' => '9ec829b8-632e-11f1-8745-fa163ea037a3',
        ]);

        $client = new Contractor([
            'name' => 'ООО "ФАРМСЕРВИС"',
            'inn' => '2312178145',
            'kpp' => '231201001',
        ]);

        $order = new Order([
            'order_number' => 'АС-ТД-486',
            'customer_rate' => '330000.00',
            'customer_payment_form' => 'vat_22',
            'order_date' => '2026-06-11',
        ]);
        $order->id = 86;
        $order->setRelation('client', $client);
        $order->setRelation('legs', collect());

        $payload = app(OneCRealizationMapper::class)->map($order);

        $this->assertSame('НДС22', $payload['service_line']['vat_rate']);
        $this->assertSame(59508.2, $payload['service_line']['vat_amount']);
        $this->assertSame('НДС22', $payload['odata_stub']['Услуги'][0]['СтавкаНДС']);
        $this->assertSame(59508.2, $payload['odata_stub']['Услуги'][0]['СуммаНДС']);
        $this->assertTrue($payload['odata_stub']['СуммаВключаетНДС']);
        $this->assertFalse($payload['odata_stub']['ДокументБезНДС']);
        $this->assertStringContainsString('оплата:', $payload['odata_stub']['Комментарий']);
    }

    public function test_optional_extra_attributes_when_configured(): void
    {
        config([
            'one_c.extra_attributes.order_id' => 'CRM_OrderId',
            'one_c.extra_attributes.order_number' => 'CRM_OrderNumber',
        ]);

        $client = new Contractor([
            'name' => 'ООО "ФАРМСЕРВИС"',
            'inn' => '2312178145',
            'kpp' => '231201001',
        ]);
        $order = new Order([
            'order_number' => 'АС-ТД-107',
            'customer_rate' => '95000.00',
            'order_date' => '2026-06-11',
        ]);
        $order->id = 19;
        $order->setRelation('client', $client);
        $order->setRelation('legs', collect());

        $payload = app(OneCRealizationMapper::class)->map($order);

        $this->assertContains(['name' => 'CRM_OrderId', 'value' => '19'], $payload['extra_attributes']);
        $this->assertContains(['name' => 'CRM_OrderNumber', 'value' => 'АС-ТД-107'], $payload['extra_attributes']);
        $this->assertArrayHasKey('ДополнительныеРеквизиты', $payload['odata_stub']);
    }

    public function test_rejects_missing_inn(): void
    {
        $this->expectException(ValidationException::class);

        $client = new Contractor(['name' => 'Без ИНН', 'inn' => null]);
        $order = new Order(['order_number' => 'X', 'customer_rate' => 1000]);
        $order->id = 1;
        $order->setRelation('client', $client);
        $order->setRelation('legs', collect());

        app(OneCRealizationMapper::class)->map($order);
    }

    public function test_rejects_legal_entity_without_kpp(): void
    {
        $this->expectException(ValidationException::class);

        $client = new Contractor([
            'name' => 'ООО Без КПП',
            'inn' => '2312178145',
            'kpp' => null,
        ]);
        $order = new Order(['order_number' => 'X', 'customer_rate' => 1000]);
        $order->id = 1;
        $order->setRelation('client', $client);
        $order->setRelation('legs', collect());

        app(OneCRealizationMapper::class)->map($order);
    }
}
