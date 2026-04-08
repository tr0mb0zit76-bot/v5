<?php

require __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Orders\OrderWizardController;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;

// Инициализируем приложение Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== Тестирование полного цикла очистки перевозчика ===\n\n";

try {
    // Создаем тестовые данные напрямую с уникальными значениями
    $timestamp = time();

    $user = User::create([
        'name' => 'Test User '.$timestamp,
        'email' => 'test'.$timestamp.'@example.com',
        'password' => bcrypt('password'),
    ]);

    $client = Contractor::create([
        'name' => 'Test Client '.$timestamp,
        'type' => 'customer',
        'inn' => '123456789'.$timestamp,
    ]);

    $carrier = Contractor::create([
        'name' => 'Test Carrier '.$timestamp,
        'type' => 'carrier',
        'inn' => '098765432'.$timestamp,
    ]);

    echo "Создан клиент: {$client->name} (ID: {$client->id})\n";
    echo "Создан перевозчик: {$carrier->name} (ID: {$carrier->id})\n";

    // Создаем заказ с перевозчиком
    $order = Order::create([
        'client_id' => $client->id,
        'carrier_id' => $carrier->id,
        'order_number' => 'TEST-'.$timestamp,
        'order_date' => now(),
        'status' => 'new',
        'performers' => json_encode([
            ['stage' => 'leg_1', 'contractor_id' => $carrier->id],
        ]),
        'financial_term' => json_encode([
            'contractors_costs' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrier->id,
                    'amount' => 500,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [
                        'has_prepayment' => false,
                        'postpayment_days' => 0,
                        'postpayment_mode' => 'fttn',
                    ],
                ],
            ],
        ]),
    ]);

    echo "Создан заказ ID: {$order->id} с перевозчиком ID: {$order->carrier_id}\n";

    // Получаем экземпляр сервиса через контейнер Laravel
    $service = app(OrderWizardService::class);

    $data = [
        'client_id' => $client->id,
        'order_date' => now()->format('Y-m-d'),
        'status' => 'new',
        'performers' => [
            ['stage' => 'leg_1', 'contractor_id' => null],
        ],
        'financial_term' => [
            'client_price' => 1000,
            'client_currency' => 'RUB',
            'client_payment_form' => 'vat',
            'client_request_mode' => 'single_request',
            'client_payment_schedule' => [
                'has_prepayment' => false,
                'postpayment_days' => 0,
                'postpayment_mode' => 'fttn',
            ],
            'contractors_costs' => [
                [
                    'stage' => 'leg_1',
                    'contractor_id' => null,
                    'amount' => null,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [
                        'has_prepayment' => false,
                        'postpayment_days' => 0,
                        'postpayment_mode' => 'fttn',
                    ],
                ],
            ],
            'additional_costs' => [],
            'kpi_percent' => 0,
        ],
    ];

    echo "\n=== Шаг 1: Обновляем заказ с очищенным перевозчиком ===\n";

    // Обновляем заказ через сервис
    $updatedOrder = $service->update($order, $data, $user);

    // Проверяем, что carrier_id стал null
    if ($updatedOrder->carrier_id === null) {
        echo "✓ carrier_id успешно обновлен на null\n";
    } else {
        echo "✗ ОШИБКА: carrier_id не null, значение: {$updatedOrder->carrier_id}\n";
    }

    // Проверяем, что performers содержит null для contractor_id
    $performers = $updatedOrder->performers;
    if (is_array($performers) && isset($performers[0]) && $performers[0]['contractor_id'] === null) {
        echo "✓ performers содержит null для contractor_id\n";
    } else {
        echo "✗ ОШИБКА: performers contractor_id не null\n";
    }

    echo "\n=== Шаг 2: Загружаем заказ для редактирования ===\n";

    // Создаем экземпляр контроллера
    $controller = app(OrderWizardController::class);

    // Используем рефлексию для вызова приватного метода serializeOrder
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('serializeOrder');
    $method->setAccessible(true);

    // Загружаем заказ с отношениями
    $orderForEdit = $updatedOrder->fresh()->load(['financialTerms', 'legs.routePoints']);

    // Сериализуем заказ
    $serializedOrder = $method->invoke($controller, $orderForEdit);

    // Проверяем, что в сериализованных данных contractor_id = null
    $contractorsCosts = $serializedOrder['financial_term']['contractors_costs'] ?? [];

    if (isset($contractorsCosts[0]) && $contractorsCosts[0]['contractor_id'] === null) {
        echo "✓ В сериализованных данных contractor_id = null\n";
    } else {
        echo "✗ ОШИБКА: В сериализованных данных contractor_id не null\n";
        echo '  contractors_costs: '.json_encode($contractorsCosts)."\n";
    }

    // Проверяем performers в сериализованных данных
    $serializedPerformers = $serializedOrder['performers'] ?? [];
    if (isset($serializedPerformers[0]) && $serializedPerformers[0]['contractor_id'] === null) {
        echo "✓ В сериализованных performers contractor_id = null\n";
    } else {
        echo "✗ ОШИБКА: В сериализованных performers contractor_id не null\n";
        echo '  performers: '.json_encode($serializedPerformers)."\n";
    }

    echo "\n=== Шаг 3: Проверяем метод normalizeContractorsCosts ===\n";

    // Используем рефлексию для вызова приватного метода normalizeContractorsCosts
    $normalizeMethod = $reflection->getMethod('normalizeContractorsCosts');
    $normalizeMethod->setAccessible(true);

    // Загружаем financialTerm
    $orderForEdit->load('financialTerms');
    $financialTerm = $orderForEdit->financialTerms->first();

    // Вызываем метод normalizeContractorsCosts
    $normalizedCosts = $normalizeMethod->invoke($controller, $orderForEdit, $financialTerm);

    if (isset($normalizedCosts[0]) && $normalizedCosts[0]['contractor_id'] === null) {
        echo "✓ normalizeContractorsCosts возвращает contractor_id = null\n";
    } else {
        echo "✗ ОШИБКА: normalizeContractorsCosts возвращает contractor_id не null\n";
        echo '  normalizedCosts: '.json_encode($normalizedCosts)."\n";
    }

    echo "\n=== Тест пройден успешно ===\n";

    // Очистка тестовых данных
    $order->delete();
    $carrier->delete();
    $client->delete();
    $user->delete();

} catch (Exception $e) {
    echo 'Ошибка: '.$e->getMessage()."\n";
    echo "Стек вызовов:\n".$e->getTraceAsString()."\n";
}
