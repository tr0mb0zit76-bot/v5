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
    public function test_maps_farmservice_etalon_order_19_shape(): void
    {
        config([
            'one_c.extra_attributes.order_id' => 'CRM_OrderId',
            'one_c.extra_attributes.order_number' => 'CRM_OrderNumber',
            'one_c.service_nomenclature.content_template' => 'Транспортные услуги по заказу {order_number}',
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
            'unloading_date' => '2026-06-18',
        ]);
        $order->id = 19;
        $order->setRelation('client', $client);

        $payload = app(OneCRealizationMapper::class)->map($order);

        $this->assertSame('РеализацияТоваровУслуг', $payload['document_type']);
        $this->assertSame('Услуги', $payload['operation_kind']);
        $this->assertSame(19, $payload['order_id']);
        $this->assertSame('АС-ТД-107', $payload['order_number']);
        $this->assertSame('95000.00', $payload['amount']);
        $this->assertSame('2026-06-18', $payload['document_date']);
        $this->assertSame('2312178145', $payload['counterparty']['inn']);
        $this->assertSame('231201001', $payload['counterparty']['kpp']);
        $this->assertSame('95000.00', $payload['service_line']['amount']);
        $this->assertSame(
            'Транспортные услуги по заказу АС-ТД-107',
            $payload['service_line']['content']
        );
        $this->assertContains(
            ['name' => 'CRM_OrderId', 'value' => '19'],
            $payload['extra_attributes']
        );
        $this->assertContains(
            ['name' => 'CRM_OrderNumber', 'value' => 'АС-ТД-107'],
            $payload['extra_attributes']
        );
        $this->assertSame('Услуги', $payload['odata_stub']['ВидОперации']);
        $this->assertSame(95000.0, $payload['odata_stub']['СуммаДокумента']);
    }

    public function test_maps_etalon_amounts_for_orders_36_and_86(): void
    {
        $mapper = app(OneCRealizationMapper::class);

        foreach ([
            [36, 'АС-ТД-213', '290000.00'],
            [86, 'АС-ТД-486', '330000.00'],
        ] as [$id, $number, $amount]) {
            $client = new Contractor([
                'name' => 'ООО "ФАРМСЕРВИС"',
                'inn' => '2312178145',
                'kpp' => '231201001',
            ]);
            $order = new Order([
                'order_number' => $number,
                'customer_rate' => $amount,
                'order_date' => '2026-06-01',
            ]);
            $order->id = $id;
            $order->setRelation('client', $client);

            $payload = $mapper->map($order);
            $this->assertSame($amount, $payload['amount']);
            $this->assertSame((string) $id, $payload['extra_attributes'][0]['value']);
        }
    }

    public function test_rejects_missing_inn(): void
    {
        $this->expectException(ValidationException::class);

        $client = new Contractor(['name' => 'Без ИНН', 'inn' => null]);
        $order = new Order(['order_number' => 'X', 'customer_rate' => 1000]);
        $order->id = 1;
        $order->setRelation('client', $client);

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

        app(OneCRealizationMapper::class)->map($order);
    }
}
