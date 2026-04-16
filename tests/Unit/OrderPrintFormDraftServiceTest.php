<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\RoutePoint;
use App\Services\DocxPlaceholderExtractor;
use App\Services\OrderPrintFormDraftService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderPrintFormDraftServiceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(OrderPrintFormDraftService $service, Order $order): array
    {
        $method = new \ReflectionMethod($service, 'buildSnapshot');
        $method->setAccessible(true);

        /** @var array<string, mixed> $snapshot */
        $snapshot = $method->invoke($service, $order);

        return $snapshot;
    }

    public function test_sender_contact_phone_is_combined_and_addresses_aggregated(): void
    {
        $service = new OrderPrintFormDraftService(new DocxPlaceholderExtractor);
        $order = new Order;

        $order->setRelation('routePoints', new Collection([
            new RoutePoint([
                'type' => 'loading',
                'address' => 'Самара, Заводская 1',
                'sender_name' => 'ООО Склад',
                'sender_contact' => 'Петров',
                'sender_phone' => '+79990000001',
                'normalized_data' => ['city' => 'Самара'],
            ]),
            new RoutePoint([
                'type' => 'loading',
                'address' => 'Самара, Складская 2',
                'sender_name' => 'ООО Склад',
                'sender_contact' => 'Петров',
                'sender_phone' => '+79990000001',
                'normalized_data' => ['city' => 'Самара'],
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'address' => 'Казань, Логистическая 10',
                'recipient_name' => 'ООО Получатель',
                'recipient_contact' => 'Иванов',
                'recipient_phone' => '+79990000002',
                'normalized_data' => ['city' => 'Казань'],
            ]),
        ]));
        $order->setRelation('cargoItems', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);

        $this->assertSame('ООО Склад', data_get($snapshot, 'cargo_sender.name'));
        $this->assertSame('Петров, +79990000001', data_get($snapshot, 'cargo_sender.contact_phone'));
        $this->assertSame('Петров, +79990000001', data_get($snapshot, 'cargo_sender.contact'));
        $this->assertSame('Петров, +79990000001', data_get($snapshot, 'cargo_sender.phone'));
        $this->assertSame('Самара, Заводская 1; Самара, Складская 2', data_get($snapshot, 'cargo_sender.all_addresses'));
        $this->assertSame('ООО Склад', data_get($snapshot, 'cargo_sender.all_names'));
    }

    public function test_sender_primary_value_uses_first_point_when_multiple_senders_present(): void
    {
        $service = new OrderPrintFormDraftService(new DocxPlaceholderExtractor);
        $order = new Order;

        $order->setRelation('routePoints', new Collection([
            new RoutePoint([
                'type' => 'loading',
                'address' => 'Москва, Первая 1',
                'sender_name' => 'ООО Первый',
                'sender_contact' => 'Сидоров',
                'sender_phone' => '+79991111111',
            ]),
            new RoutePoint([
                'type' => 'loading',
                'address' => 'Москва, Вторая 2',
                'sender_name' => 'ООО Второй',
                'sender_contact' => 'Смирнов',
                'sender_phone' => '+79992222222',
            ]),
        ]));
        $order->setRelation('cargoItems', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);

        $this->assertSame('ООО Первый', data_get($snapshot, 'cargo_sender.name'));
        $this->assertSame('ООО Первый; ООО Второй', data_get($snapshot, 'cargo_sender.all_names'));
        $this->assertSame(
            'Сидоров, +79991111111; Смирнов, +79992222222',
            data_get($snapshot, 'cargo_sender.all_contact_phones')
        );
    }
}
