<?php

require __DIR__.'/vendor/autoload.php';

use App\Models\Contractor;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Инициализируем приложение Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== Простой тест отображения перевозчика в таблице заказов ===\n\n";

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
    ]);

    echo "Создан заказ ID: {$order->id} с перевозчиком ID: {$order->carrier_id}\n";

    echo "\n=== Шаг 1: Проверяем SQL запрос для таблицы заказов ===\n";

    // Выполняем тот же запрос, что и в OrderIndexController
    $result = DB::table('orders')
        ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
        ->select('orders.carrier_id', 'carriers.name as carrier_name')
        ->where('orders.id', $order->id)
        ->first();

    echo "  Результат SQL запроса:\n";
    echo '  carrier_id: '.($result->carrier_id ?? 'null')."\n";
    echo '  carrier_name: '.($result->carrier_name ?? 'null')."\n";

    if ($result->carrier_id == $carrier->id && $result->carrier_name == $carrier->name) {
        echo "✓ Данные корректны: перевозчик отображается\n";
    } else {
        echo "✗ ОШИБКА: Данные не совпадают\n";
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

    echo '  carrier_id после обновления: '.($updatedOrder->carrier_id ?? 'null')."\n";

    if ($updatedOrder->carrier_id === null) {
        echo "✓ carrier_id успешно обновлен на null\n";
    } else {
        echo "✗ ОШИБКА: carrier_id не null\n";
    }

    echo "\n=== Шаг 3: Проверяем SQL запрос после обновления ===\n";

    // Снова выполняем SQL запрос
    $resultAfterUpdate = DB::table('orders')
        ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
        ->select('orders.carrier_id', 'carriers.name as carrier_name')
        ->where('orders.id', $order->id)
        ->first();

    echo "  Результат SQL запроса после обновления:\n";
    echo '  carrier_id: '.($resultAfterUpdate->carrier_id ?? 'null')."\n";
    echo '  carrier_name: '.($resultAfterUpdate->carrier_name ?? 'null')."\n";

    if ($resultAfterUpdate->carrier_id === null && $resultAfterUpdate->carrier_name === null) {
        echo "✓ Данные корректны: перевозчик очищен\n";
    } elseif ($resultAfterUpdate->carrier_id === null && $resultAfterUpdate->carrier_name !== null) {
        echo "✗ ОШИБКА: carrier_id = null, но carrier_name не null\n";
        echo "  Это означает, что LEFT JOIN все еще находит запись в таблице contractors.\n";
        echo "  Возможные причины:\n";
        echo "  1. carrier_id в orders не равен null\n";
        echo "  2. Есть другая запись в contractors с id = null (невозможно)\n";
        echo "  3. Проблема с кэшированием запроса\n";
    } else {
        echo "✗ ОШИБКА: carrier_id не null\n";
    }

    echo "\n=== Шаг 4: Проверяем данные напрямую в таблице orders ===\n";

    $directOrder = Order::find($order->id);
    echo "  Прямое чтение модели Order:\n";
    echo '  carrier_id: '.($directOrder->carrier_id ?? 'null')."\n";
    echo '  performers: '.json_encode($directOrder->performers ?? [])."\n";

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
