<?php

require __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Orders\OrderIndexController;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Инициализируем приложение Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== Тестирование отображения перевозчика в таблице заказов ===\n\n";

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

    echo "\n=== Шаг 1: Проверяем отображение перевозчика в таблице ===\n";

    // Создаем экземпляр контроллера
    $controller = app(OrderIndexController::class);

    // Создаем mock запрос
    $request = Request::create('/', 'GET');
    $request->setUserResolver(function () use ($user) {
        return $user;
    });

    // Вызываем контроллер и получаем данные через рефлексию
    $response = $controller($request);

    // Получаем приватное свойство $props через рефлексию
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $data = $propsProperty->getValue($response);

    $rows = $data['rows'] ?? [];

    // Находим наш заказ в таблице
    $orderRow = null;
    foreach ($rows as $row) {
        if ($row['id'] == $order->id) {
            $orderRow = $row;
            break;
        }
    }

    if ($orderRow) {
        echo "✓ Заказ найден в таблице\n";
        echo '  carrier_id в таблице: '.($orderRow['carrier_id'] ?? 'null')."\n";
        echo '  carrier_name в таблице: '.($orderRow['carrier_name'] ?? 'null')."\n";

        if ($orderRow['carrier_name'] === $carrier->name) {
            echo "✓ Название перевозчика корректно отображается: {$carrier->name}\n";
        } else {
            echo "✗ ОШИБКА: Название перевозчика не совпадает\n";
        }
    } else {
        echo "✗ ОШИБКА: Заказ не найден в таблице\n";
    }

    echo "\n=== Шаг 2: Обновляем заказ с очищенным перевозчиком ===\n";

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

    // Обновляем заказ через сервис
    $updatedOrder = $service->update($order, $data, $user);

    if ($updatedOrder->carrier_id === null) {
        echo "✓ carrier_id успешно обновлен на null\n";
    } else {
        echo "✗ ОШИБКА: carrier_id не null, значение: {$updatedOrder->carrier_id}\n";
    }

    echo "\n=== Шаг 3: Проверяем отображение в таблице после обновления ===\n";

    // Снова вызываем контроллер
    $response = $controller($request);
    $data = $response->getData()['props'];
    $rows = $data['rows'] ?? [];

    // Находим обновленный заказ в таблице
    $updatedOrderRow = null;
    foreach ($rows as $row) {
        if ($row['id'] == $order->id) {
            $updatedOrderRow = $row;
            break;
        }
    }

    if ($updatedOrderRow) {
        echo "✓ Обновленный заказ найден в таблице\n";
        echo '  carrier_id в таблице: '.($updatedOrderRow['carrier_id'] ?? 'null')."\n";
        echo '  carrier_name в таблице: '.($updatedOrderRow['carrier_name'] ?? 'null')."\n";

        if ($updatedOrderRow['carrier_id'] === null && $updatedOrderRow['carrier_name'] === null) {
            echo "✓ Название перевозчика корректно очищено (null)\n";
        } elseif ($updatedOrderRow['carrier_id'] === null && $updatedOrderRow['carrier_name'] !== null) {
            echo "✗ ОШИБКА: carrier_id = null, но carrier_name не null: {$updatedOrderRow['carrier_name']}\n";
            echo "  Это может быть связано с кэшированием данных или проблемой в запросе.\n";
        } else {
            echo "✗ ОШИБКА: carrier_id не null: {$updatedOrderRow['carrier_id']}\n";
        }
    } else {
        echo "✗ ОШИБКА: Обновленный заказ не найден в таблице\n";
    }

    echo "\n=== Шаг 4: Проверяем SQL запрос напрямую ===\n";

    // Проверяем данные напрямую через SQL
    $directResult = DB::table('orders')
        ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
        ->select('orders.carrier_id', 'carriers.name as carrier_name')
        ->where('orders.id', $order->id)
        ->first();

    if ($directResult) {
        echo "  Прямой SQL запрос:\n";
        echo '  carrier_id: '.($directResult->carrier_id ?? 'null')."\n";
        echo '  carrier_name: '.($directResult->carrier_name ?? 'null')."\n";

        if ($directResult->carrier_id === null && $directResult->carrier_name === null) {
            echo "✓ SQL запрос подтверждает, что данные корректны\n";
        } else {
            echo "✗ ОШИБКА: SQL запрос показывает несоответствие\n";
        }
    }

    echo "\n=== Тест завершен ===\n";

    // Очистка тестовых данных
    $order->delete();
    $carrier->delete();
    $client->delete();
    $user->delete();

} catch (Exception $e) {
    echo 'Ошибка: '.$e->getMessage()."\n";
    echo "Стек вызовов:\n".$e->getTraceAsString()."\n";
}
