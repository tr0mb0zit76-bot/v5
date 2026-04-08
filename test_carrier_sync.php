<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "Тест синхронизации исполнителей\n";
echo "================================\n\n";

// Найдем существующий заказ для тестирования
$order = Order::query()->first();
if (! $order) {
    echo "Нет заказов для тестирования\n";
    exit(1);
}

echo "Заказ ID: {$order->id}\n";
echo 'Текущий carrier_id: '.($order->carrier_id ?? 'null')."\n";
echo 'Текущие performers: '.json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE)."\n\n";

// Создаем тестовые данные с пустым исполнителем
$testData = [
    'status' => $order->status,
    'client_id' => $order->customer_id,
    'own_company_id' => $order->own_company_id,
    'order_date' => $order->order_date?->toDateString(),
    'order_number' => $order->order_number,
    'special_notes' => $order->special_notes,
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => null, // Пустой исполнитель
        ],
    ],
    'route_points' => [],
    'cargo_items' => [],
    'financial_term' => [
        'client_price' => $order->customer_rate,
        'client_currency' => 'RUB',
        'client_payment_form' => $order->customer_payment_form,
        'client_request_mode' => 'single_request',
        'client_payment_schedule' => [],
        'contractors_costs' => [
            [
                'stage' => 'leg_1',
                'contractor_id' => null, // Пустой исполнитель
                'amount' => null,
                'currency' => 'RUB',
                'payment_form' => 'no_vat',
                'payment_schedule' => [],
            ],
        ],
        'additional_costs' => [],
        'kpi_percent' => 0,
    ],
    'documents' => [],
];

echo "Тестовые данные для обновления:\n";
echo 'performers[0].contractor_id: '.($testData['performers'][0]['contractor_id'] ?? 'null')."\n";
echo 'financial_term.contractors_costs[0].contractor_id: '.($testData['financial_term']['contractors_costs'][0]['contractor_id'] ?? 'null')."\n\n";

// Получаем сервис
$orderWizardService = app(OrderWizardService::class);
$user = $order->manager ?? User::first();

echo "Обновляем заказ...\n";

try {
    DB::beginTransaction();

    $updatedOrder = $orderWizardService->update($order, $testData, $user);

    DB::rollBack(); // Откатываем транзакцию, чтобы не изменять реальные данные

    echo "\nРезультат обновления:\n";
    echo 'carrier_id: '.($updatedOrder->carrier_id ?? 'null')."\n";
    echo 'performers: '.json_encode($updatedOrder->performers ?? [], JSON_UNESCAPED_UNICODE)."\n";

    // Проверяем, что carrier_id действительно null
    if ($updatedOrder->carrier_id === null) {
        echo "\n✓ УСПЕХ: carrier_id успешно установлен в null\n";
    } else {
        echo "\n✗ ОШИБКА: carrier_id не null: {$updatedOrder->carrier_id}\n";
    }

} catch (Exception $e) {
    DB::rollBack();
    echo "\n✗ ОШИБКА при обновлении: ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}

echo "\nТест завершен.\n";
