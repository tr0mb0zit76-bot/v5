<?php

namespace Tests\Unit;

use App\Models\Cargo;
use App\Models\Order;
use App\Models\RoutePoint;
use App\Services\DocxPlaceholderExtractor;
use App\Services\OrderPrintFormDraftService;
use App\Services\PrintFormVariableCatalog;
use App\Support\PrintFormPlaceholderPathResolver;
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
        $service = new OrderPrintFormDraftService(new DocxPlaceholderExtractor, new PrintFormPlaceholderPathResolver);
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
        $service = new OrderPrintFormDraftService(new DocxPlaceholderExtractor, new PrintFormPlaceholderPathResolver);
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

    public function test_route_city_falls_back_to_address_and_time_range_is_exposed(): void
    {
        $service = new OrderPrintFormDraftService(new DocxPlaceholderExtractor, new PrintFormPlaceholderPathResolver);
        $order = new Order;

        $order->setRelation('routePoints', new Collection([
            new RoutePoint([
                'type' => 'loading',
                'address' => 'Тольятти, Южное шоссе, 12',
                'planned_time_from' => '09:00:00',
                'planned_time_to' => '11:30:00',
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'address' => 'г. Казань, ул. Баумана, 1',
                'planned_time_from' => '15:00:00',
                'planned_time_to' => '17:00:00',
            ]),
        ]));
        $order->setRelation('cargoItems', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);

        $this->assertSame('Тольятти', data_get($snapshot, 'route.loading_first_city'));
        $this->assertSame('Казань', data_get($snapshot, 'route.unloading_first_city'));
        $this->assertSame('09:00-11:30', data_get($snapshot, 'route.loading_time_range'));
        $this->assertSame('15:00-17:00', data_get($snapshot, 'route.unloading_time_range'));
        $this->assertArrayNotHasKey('loading_time_from', $snapshot['route']);
        $this->assertArrayNotHasKey('loading_time_to', $snapshot['route']);
        $this->assertArrayNotHasKey('unloading_time_from', $snapshot['route']);
        $this->assertArrayNotHasKey('unloading_time_to', $snapshot['route']);
    }

    public function test_cargo_transport_requirement_values_are_exposed_for_print_forms(): void
    {
        $service = new OrderPrintFormDraftService(new DocxPlaceholderExtractor, new PrintFormPlaceholderPathResolver);
        $order = new Order;

        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection([
            new Cargo([
                'cargo_type_label' => 'Стройматериалы',
                'pack_type_label' => 'Паллеты',
                'loading_type_items' => [
                    ['label' => 'Боковая'],
                    ['label' => 'Верхняя'],
                ],
                'truck_body_type_items' => [
                    ['label' => 'Тентованный'],
                ],
                'trailer_type_items' => [
                    ['label' => 'Полуприцеп'],
                ],
            ]),
            new Cargo([
                'cargo_type_label' => 'Оборудование',
                'pack_type_label' => 'Ящики',
                'loading_type_label' => 'Задняя',
                'truck_body_type_label' => 'Открытая',
                'trailer_type_label' => 'Прицеп',
            ]),
        ]));

        $snapshot = $this->buildSnapshot($service, $order);
        $catalogValues = collect((new PrintFormVariableCatalog)->orderOptions())
            ->pluck('value')
            ->all();

        $this->assertSame('Стройматериалы, Оборудование', data_get($snapshot, 'cargo.cargo_types'));
        $this->assertSame('Паллеты, Ящики', data_get($snapshot, 'cargo.pack_types'));
        $this->assertSame('Боковая, Верхняя, Задняя', data_get($snapshot, 'cargo.loading_types'));
        $this->assertSame('Тентованный, Открытая', data_get($snapshot, 'cargo.truck_body_types'));
        $this->assertSame('Полуприцеп, Прицеп', data_get($snapshot, 'cargo.trailer_types'));
        $this->assertContains('cargo.loading_types', $catalogValues);
        $this->assertContains('cargo.truck_body_types', $catalogValues);
        $this->assertContains('cargo.trailer_types', $catalogValues);
        $this->assertContains('cargo.cargo_types', $catalogValues);
        $this->assertContains('cargo.pack_types', $catalogValues);
    }
}
