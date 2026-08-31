<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Services\OneC\OneCEpdStubMapper;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OneCEpdStubMapperTest extends TestCase
{
    public function test_maps_etrn_without_customer_rate(): void
    {
        $client = new Contractor([
            'name' => 'ООО "ФАРМСЕРВИС"',
            'inn' => '2312178145',
            'kpp' => '231201001',
            'phone' => '+79001112233',
        ]);
        $carrier = new Contractor([
            'name' => 'ООО Перевоз',
            'inn' => '7707083893',
            'kpp' => '770701001',
            'phone' => '+79004445566',
        ]);
        $carrier->id = 9;

        $order = new Order([
            'order_number' => 'EPD-1',
            'customer_rate' => null,
            'order_date' => '2026-08-10',
            'loading_date' => '2026-08-11',
            'carrier_id' => 9,
        ]);
        $order->id = 501;
        $order->setRelation('client', $client);
        $order->setRelation('carrier', $carrier);
        $order->setRelation('legs', collect());
        $order->setRelation('routePoints', collect([
            (object) [
                'type' => 'loading',
                'address' => 'Саратов',
                'planned_date' => Carbon::parse('2026-08-11'),
                'planned_time_from' => null,
                'planned_time_to' => null,
            ],
            (object) [
                'type' => 'unloading',
                'address' => 'Волгоград',
                'planned_date' => Carbon::parse('2026-08-12'),
                'planned_time_from' => null,
                'planned_time_to' => null,
            ],
        ]));
        $order->setRelation('cargoItems', collect([
            (object) [
                'title' => 'каток',
                'weight' => '14000.00',
                'volume' => '42.32',
                'package_count' => null,
            ],
        ]));

        $payload = app(OneCEpdStubMapper::class)->map($order, OrderOneCDocument::TYPE_ETRN);

        $this->assertSame('etrn', $payload['document_type']);
        $this->assertSame(501, $payload['order_id']);
        $this->assertSame('EPD-1', $payload['order_number']);
        $this->assertSame('2312178145', $payload['counterparty']['inn']);
        $this->assertSame('+79001112233', $payload['counterparty']['phone']);
        $this->assertSame('+79004445566', $payload['parties']['carrier']['phone']);
        $this->assertSame('ООО Перевоз', $payload['parties']['carrier']['name']);
        $this->assertStringContainsString('CRM EPD-1 (id 501)', $payload['odata_stub']['Комментарий']);
        $this->assertStringContainsString('погр: Саратов 2026-08-11', $payload['odata_stub']['Комментарий']);
        $this->assertStringContainsString('выгр: Волгоград 2026-08-12', $payload['odata_stub']['Комментарий']);
        $this->assertStringContainsString('груз: каток 14000кг', $payload['odata_stub']['Комментарий']);
        $this->assertLessThanOrEqual(250, mb_strlen((string) $payload['odata_stub']['Комментарий']));
        $this->assertSame('EPD-1', $payload['odata_stub']['ТитулГрузоотправителяТранспортнаяНакладнаяНомер']);
        $this->assertFalse($payload['odata_stub']['Posted']);
        $this->assertArrayNotHasKey('amount', $payload);
        $this->assertArrayNotHasKey('vat', $payload);
        $this->assertArrayNotHasKey('Контрагент_Key', $payload['odata_stub']);
    }

    public function test_maps_expedition_receipt(): void
    {
        $client = new Contractor([
            'name' => 'ИП Клиент',
            'inn' => '632111465177',
        ]);

        $order = new Order([
            'order_number' => 'EPD-2',
            'order_date' => '2026-08-12',
        ]);
        $order->id = 502;
        $order->setRelation('client', $client);
        $order->setRelation('carrier', null);
        $order->setRelation('legs', collect());
        $order->setRelation('routePoints', collect());
        $order->setRelation('cargoItems', collect());

        $payload = app(OneCEpdStubMapper::class)->map($order, OrderOneCDocument::TYPE_EXPEDITION_RECEIPT);

        $this->assertSame('expedition_receipt', $payload['document_type']);
        $this->assertSame('632111465177', $payload['counterparty']['inn']);
        $this->assertSame('CRM EPD-2 (id 502)', $payload['odata_stub']['Комментарий']);
    }

    public function test_rejects_invalid_customer_inn(): void
    {
        $client = new Contractor([
            'name' => 'Bad',
            'inn' => '123',
        ]);
        $order = new Order(['order_number' => 'X']);
        $order->id = 1;
        $order->setRelation('client', $client);
        $order->setRelation('legs', collect());
        $order->setRelation('routePoints', collect());
        $order->setRelation('cargoItems', collect());

        $this->expectException(ValidationException::class);
        app(OneCEpdStubMapper::class)->map($order, OrderOneCDocument::TYPE_ETRN);
    }
}
