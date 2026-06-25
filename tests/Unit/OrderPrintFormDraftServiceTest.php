<?php

namespace Tests\Unit;

use App\Models\Cargo;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\PrintFormTemplate;
use App\Models\RoutePoint;
use App\Services\OrderPrintFormDraftService;
use App\Services\PrintFormVariableCatalog;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderPrintFormDraftServiceTest extends TestCase
{
    private function makeService(): OrderPrintFormDraftService
    {
        return app(OrderPrintFormDraftService::class);
    }

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
        $service = $this->makeService();
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
        $service = $this->makeService();
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
        $service = $this->makeService();
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
        $service = $this->makeService();
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

    public function test_route_special_conditions_are_exposed_from_performers(): void
    {
        $service = $this->makeService();
        $order = new Order([
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'loading_special_conditions' => 'Пропуск на территорию за сутки',
                    'unloading_special_conditions' => 'Выгрузка только до 18:00',
                ],
                [
                    'stage' => 'leg_2',
                    'loading_special_conditions' => 'Кран на погрузке',
                    'unloading_special_conditions' => 'Контакт на воротах: Иванов',
                ],
            ],
        ]);

        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);
        $catalogValues = collect((new PrintFormVariableCatalog)->orderOptions())
            ->pluck('value')
            ->all();

        $this->assertSame(
            "Пропуск на территорию за сутки\n\nКран на погрузке",
            data_get($snapshot, 'route.loading_special_conditions'),
        );
        $this->assertSame(
            "Выгрузка только до 18:00\n\nКонтакт на воротах: Иванов",
            data_get($snapshot, 'route.unloading_special_conditions'),
        );
        $this->assertContains('route.loading_special_conditions', $catalogValues);
        $this->assertContains('route.unloading_special_conditions', $catalogValues);
    }

    public function test_contractor_postal_address_and_signer_position_are_exposed_for_print_forms(): void
    {
        $service = $this->makeService();
        $order = new Order;
        $customer = new Contractor([
            'name' => 'ООО Клиент',
            'postal_address' => '443000, Самара, а/я 15',
            'contact_person_position' => 'Коммерческий директор',
            'signer_position' => 'Генеральный директор',
        ]);
        $carrier = new Contractor([
            'name' => 'ООО Перевозчик',
            'postal_address' => '420000, Казань, а/я 7',
            'contact_person_position' => 'Директор по логистике',
        ]);

        $order->setRelation('client', $customer);
        $order->setRelation('carrier', $carrier);
        $order->setRelation('ownCompany', null);
        $order->setRelation('manager', null);
        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);
        $catalogValues = collect((new PrintFormVariableCatalog)->orderOptions())
            ->pluck('value')
            ->all();

        $this->assertSame('443000, Самара, а/я 15', data_get($snapshot, 'customer.postal_address'));
        $this->assertSame('Генеральный директор', data_get($snapshot, 'customer.signer_position'));
        $this->assertSame('420000, Казань, а/я 7', data_get($snapshot, 'carrier.postal_address'));
        $this->assertSame('Директор по логистике', data_get($snapshot, 'carrier.signer_position'));
        $this->assertContains('customer.postal_address', $catalogValues);
        $this->assertContains('customer.signer_position', $catalogValues);
        $this->assertContains('carrier.postal_address', $catalogValues);
        $this->assertContains('carrier.signer_position', $catalogValues);
        $this->assertContains('own_company.postal_address', $catalogValues);
        $this->assertContains('own_company.signer_position', $catalogValues);
    }

    public function test_builds_route_point_table_rows_for_each_loading_and_unloading_point(): void
    {
        $service = $this->makeService();
        $order = new Order;

        $order->setRelation('routePoints', new Collection([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'address' => 'Самара, Заводская 1',
                'sender_name' => 'ООО Отправитель',
                'normalized_data' => ['city' => 'Самара'],
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'address' => 'Казань, Логистическая 10',
                'recipient_name' => 'ООО Получатель 1',
                'normalized_data' => ['city' => 'Казань'],
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 3,
                'address' => 'Москва, Склад 5',
                'recipient_name' => 'ООО Получатель 2',
                'normalized_data' => ['city' => 'Москва'],
            ]),
        ]));
        $order->setRelation('legs', new Collection);
        $order->setRelation('cargoItems', new Collection);

        $method = new \ReflectionMethod($service, 'buildRoutePointTableRowsForTemplate');
        $method->setAccessible(true);

        /** @var list<array<string, string>> $rows */
        $rows = $method->invoke($service, $order, null);

        $this->assertCount(3, $rows);
        $this->assertSame('1', $rows[0]['route_point_row_index']);
        $this->assertSame('Погрузка', $rows[0]['route_point_row_type_label']);
        $this->assertSame('2', $rows[1]['route_point_row_index']);
        $this->assertSame('Казань, Логистическая 10', $rows[1]['route_point_row_address']);
        $this->assertSame('3', $rows[2]['route_point_row_index']);
        $this->assertSame('Москва, Склад 5', $rows[2]['route_point_row_address']);
    }

    public function test_route_point_table_rows_include_special_conditions_from_performer_stage(): void
    {
        $service = $this->makeService();
        $order = new Order([
            'performers' => [
                [
                    'stage' => 'leg_1',
                    'loading_special_conditions' => 'Пропуск за сутки',
                    'unloading_special_conditions' => 'Выгрузка до 18:00',
                ],
            ],
        ]);

        $order->setRelation('legs', new Collection([
            new OrderLeg(['id' => 10, 'sequence' => 1, 'description' => 'leg_1']),
        ]));
        $order->setRelation('routePoints', new Collection([
            new RoutePoint([
                'order_leg_id' => 10,
                'type' => 'loading',
                'sequence' => 1,
                'address' => 'Самара, Заводская 1',
                'sender_name' => 'ООО Отправитель',
                'normalized_data' => ['city' => 'Самара'],
            ]),
            new RoutePoint([
                'order_leg_id' => 10,
                'type' => 'unloading',
                'sequence' => 2,
                'address' => 'Казань, Логистическая 10',
                'recipient_name' => 'ООО Получатель',
                'normalized_data' => ['city' => 'Казань'],
            ]),
        ]));
        $order->setRelation('cargoItems', new Collection);

        $method = new \ReflectionMethod($service, 'buildRoutePointTableRowsForTemplate');
        $method->setAccessible(true);

        /** @var list<array<string, string>> $rows */
        $rows = $method->invoke($service, $order, null);

        $this->assertCount(2, $rows);
        $this->assertSame('Пропуск за сутки', $rows[0]['route_point_row_special_conditions']);
        $this->assertSame('Выгрузка до 18:00', $rows[1]['route_point_row_special_conditions']);
    }

    public function test_route_point_table_rows_read_special_conditions_from_wizard_state(): void
    {
        $service = $this->makeService();
        $order = new Order;
        $order->forceFill([
            'wizard_state' => [
                'performers' => [[
                    'stage' => 'leg_1',
                    'loading_special_conditions' => 'Верхняя',
                    'unloading_special_conditions' => 'Верхняя',
                ]],
            ],
        ]);

        $order->setRelation('legs', new Collection([
            new OrderLeg(['id' => 467, 'sequence' => 1, 'description' => 'leg_1']),
        ]));
        $order->setRelation('routePoints', new Collection([
            new RoutePoint([
                'order_leg_id' => 467,
                'type' => 'loading',
                'sequence' => 1,
                'address' => 'Калужская обл, г Обнинск',
            ]),
            new RoutePoint([
                'order_leg_id' => 467,
                'type' => 'unloading',
                'sequence' => 2,
                'address' => 'Ленинградская обл, поселок Новогорелово',
            ]),
        ]));
        $order->setRelation('cargoItems', new Collection);

        $method = new \ReflectionMethod($service, 'buildRoutePointTableRowsForTemplate');
        $method->setAccessible(true);

        /** @var list<array<string, string>> $rows */
        $rows = $method->invoke($service, $order, null);

        $this->assertSame('Верхняя', $rows[0]['route_point_row_special_conditions']);
        $this->assertSame('Верхняя', $rows[1]['route_point_row_special_conditions']);
    }

    public function test_uses_carrier_portal_submission_for_driver_and_vehicle_when_fleet_ids_missing(): void
    {
        $service = $this->makeService();
        $order = new Order([
            'performers' => [[
                'stage' => 'leg_1',
                'carrier_portal_submission' => [
                    'driver_full_name' => 'Петров Пётр Петрович',
                    'driver_phone' => '+79991234567',
                    'driver_license' => '77 AA 123456',
                    'tractor_plate' => 'A123BC77',
                    'tractor_brand' => 'MAN',
                    'trailer_plate' => 'BB123477',
                    'trailer_brand' => 'Schmitz',
                ],
            ]],
        ]);

        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection);
        $order->setRelation('legs', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);

        $this->assertSame('Петров Пётр Петрович', data_get($snapshot, 'driver.full_name'));
        $this->assertSame('+79991234567', data_get($snapshot, 'driver.phone'));
        $this->assertSame('A123BC77', data_get($snapshot, 'vehicle.number'));
        $this->assertSame('Schmitz', data_get($snapshot, 'vehicle.trailer_brand'));
        $this->assertSame('BB123477', data_get($snapshot, 'vehicle.trailer_plate'));
    }

    public function test_uses_carrier_portal_submission_plates_without_driver_name(): void
    {
        $service = $this->makeService();
        $order = new Order([
            'performers' => [[
                'stage' => 'leg_1',
                'carrier_portal_submission' => [
                    'tractor_plate' => 'X111XX77',
                    'trailer_plate' => 'YY222277',
                    'trailer_brand' => 'Krone',
                ],
            ]],
        ]);

        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection);
        $order->setRelation('legs', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);

        $this->assertSame('X111XX77', data_get($snapshot, 'vehicle.number'));
        $this->assertSame('YY222277', data_get($snapshot, 'vehicle.trailer_plate'));
        $this->assertSame('Krone', data_get($snapshot, 'vehicle.trailer_brand'));
    }

    public function test_uses_wizard_state_performers_when_order_performers_empty(): void
    {
        $service = $this->makeService();
        $order = new Order;
        $order->forceFill([
            'wizard_state' => [
                'performers' => [[
                    'stage' => 'leg_1',
                    'carrier_portal_submission' => [
                        'tractor_plate' => 'M555MM99',
                    ],
                ]],
            ],
        ]);

        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection);
        $order->setRelation('legs', new Collection);

        $snapshot = $this->buildSnapshot($service, $order);

        $this->assertSame('M555MM99', data_get($snapshot, 'vehicle.number'));
    }

    public function test_legacy_gosnomer_includes_trailer_plate_when_template_has_no_trailer_placeholder(): void
    {
        $service = $this->makeService();
        $method = new \ReflectionMethod($service, 'applyLegacyVehiclePlaceholderEnrichment');
        $method->setAccessible(true);

        $placeholders = new Collection(['gosnomer', 'marka_avto', 'tip_pritsepa']);
        $snapshot = [
            'vehicle' => [
                'number' => 'М 816 СН/21',
                'trailer_plate' => 'АЕ 8447/21',
                'brand' => 'Ивеко',
                'trailer_brand' => null,
            ],
        ];

        $gosnomer = $method->invoke($service, 'gosnomer', $placeholders, $snapshot, 'М 816 СН/21');
        $marka = $method->invoke($service, 'marka_avto', $placeholders, $snapshot, 'Ивеко');

        $this->assertSame('М 816 СН/21 / АЕ 8447/21', $gosnomer);
        $this->assertSame('Ивеко', $marka);
    }

    public function test_legacy_gosnomer_does_not_duplicate_trailer_when_template_has_gosnomer_priz(): void
    {
        $service = $this->makeService();
        $method = new \ReflectionMethod($service, 'applyLegacyVehiclePlaceholderEnrichment');
        $method->setAccessible(true);

        $placeholders = new Collection(['gosnomer', 'gosnomer_priz']);
        $snapshot = [
            'vehicle' => [
                'number' => 'М 816 СН/21',
                'trailer_plate' => 'АЕ 8447/21',
            ],
        ];

        $gosnomer = $method->invoke($service, 'gosnomer', $placeholders, $snapshot, 'М 816 СН/21');

        $this->assertSame('М 816 СН/21', $gosnomer);
    }

    public function test_legacy_gosnomer_ts_resolves_vehicle_number_despite_identity_template_mapping(): void
    {
        $service = $this->makeService();
        $method = new \ReflectionMethod($service, 'resolvePlaceholderReplacement');
        $method->setAccessible(true);

        $placeholders = new Collection(['gosnomer_TS', 'marka_avto']);
        $mapping = collect(['gosnomer_TS' => 'gosnomer_TS']);
        $template = new PrintFormTemplate([
            'party' => 'customer',
            'code' => 'test',
        ]);
        $snapshot = [
            'vehicle' => [
                'number' => 'С357ХК797',
                'brand' => 'Dongfeng',
                'trailer_plate' => null,
            ],
        ];

        $replacement = $method->invoke(
            $service,
            'gosnomer_TS',
            $placeholders,
            $mapping,
            $template,
            $snapshot,
        );

        $this->assertSame('С357ХК797', $replacement);
    }
}
