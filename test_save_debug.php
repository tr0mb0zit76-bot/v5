<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCompensationService;
use App\Services\OrderNumberGenerator;
use App\Services\OrderStatusService;
use App\Services\OrderWizardService;

// Создаем тестовый заказ
$order = Order::find(2510); // OOOL-2604-0003

if (! $order) {
    echo "Заказ с ID 2510 не найден\n";
    exit;
}

echo "Текущий заказ: {$order->order_number}\n";
echo 'Текущий carrier_id: '.($order->carrier_id ?? 'null')."\n";
echo 'Текущий performers: '.json_encode($order->performers, JSON_PRETTY_PRINT)."\n\n";

// Тестовые данные для обновления
$validated = [
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => 999, // Новый перевозчик
        ],
    ],
    'financial_term' => [
        'contractors_costs' => [
            [
                'stage' => 'leg_1',
                'contractor_id' => 999, // Должен совпадать
                'amount' => 50000,
                'currency' => 'RUB',
                'payment_form' => 'no_vat',
                'payment_schedule' => [],
            ],
        ],
        'client_price' => 100000,
        'client_currency' => 'RUB',
        'client_payment_form' => 'vat',
        'kpi_percent' => 0,
    ],
    'client_id' => $order->customer_id,
    'own_company_id' => $order->own_company_id,
    'status' => $order->status,
    'order_date' => $order->order_date?->toDateString(),
];

echo "Тестовые данные для обновления:\n";
echo json_encode($validated, JSON_PRETTY_PRINT)."\n\n";

// Проверяем extractOrderAttributes
$service = new OrderWizardService(
    new OrderNumberGenerator,
    new OrderStatusService,
    new OrderCompensationService
);

// Создаем мок пользователя
$user = new User;
$user->id = 1;

$numberData = ['company_code' => 'OOOL', 'order_number' => '2604-0003'];

$attributes = $service->extractOrderAttributes($validated, $user, $numberData, false);

echo "Результат extractOrderAttributes:\n";
echo 'carrier_id: '.($attributes['carrier_id'] ?? 'null')."\n";
echo 'performers: '.json_encode($attributes['performers'], JSON_PRETTY_PRINT)."\n\n";

echo "Вывод: если carrier_id = 999 и performers содержит contractor_id = 999, то сохранение должно работать.\n";
echo "Если нет - проблема в extractOrderAttributes.\n";
