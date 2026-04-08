<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Http\Controllers\Orders\OrderWizardController;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "Тест сохранения изменений исполнителя\n";
echo "======================================\n\n";

// Найдем существующий заказ для тестирования
$order = Order::query()->first();
if (! $order) {
    echo "Нет заказов для тестирования\n";
    exit(1);
}

echo "Исходное состояние заказа ID: {$order->id}\n";
echo 'carrier_id: '.($order->carrier_id ?? 'null')."\n";
echo 'performers: '.json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE)."\n\n";

// Создаем тестовые данные с исполнителем
$testContractor = Contractor::query()->where('id', '!=', $order->customer_id)->first();
if (! $testContractor) {
    echo "Нет контрагентов для тестирования\n";
    exit(1);
}

echo "Тестовый контрагент ID: {$testContractor->id}, Name: {$testContractor->name}\n\n";

// Тест 1: Устанавливаем исполнителя
echo "=== Тест 1: Устанавливаем исполнителя ===\n";
$testData1 = [
    'status' => $order->status,
    'client_id' => $order->customer_id,
    'own_company_id' => $order->own_company_id,
    'order_date' => $order->order_date?->toDateString(),
    'order_number' => $order->order_number,
    'special_notes' => $order->special_notes,
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => $testContractor->id,
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
                'contractor_id' => $testContractor->id,
                'amount' => 1000,
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

$orderWizardService = app(OrderWizardService::class);
$user = $order->manager ?? User::first();

try {
    DB::beginTransaction();

    $updatedOrder1 = $orderWizardService->update($order, $testData1, $user);

    echo "После установки исполнителя:\n";
    echo 'carrier_id: '.($updatedOrder1->carrier_id ?? 'null')." (ожидается: {$testContractor->id})\n";
    echo 'performers: '.json_encode($updatedOrder1->performers ?? [], JSON_UNESCAPED_UNICODE)."\n";

    if ($updatedOrder1->carrier_id == $testContractor->id) {
        echo "✓ УСПЕХ: carrier_id успешно установлен\n";
    } else {
        echo "✗ ОШИБКА: carrier_id не установлен\n";
    }

    // Тест 2: Удаляем исполнителя
    echo "\n=== Тест 2: Удаляем исполнителя ===\n";
    $testData2 = $testData1;
    $testData2['performers'][0]['contractor_id'] = null;
    $testData2['financial_term']['contractors_costs'][0]['contractor_id'] = null;

    $updatedOrder2 = $orderWizardService->update($updatedOrder1, $testData2, $user);

    echo "После удаления исполнителя:\n";
    echo 'carrier_id: '.($updatedOrder2->carrier_id ?? 'null')." (ожидается: null)\n";
    echo 'performers: '.json_encode($updatedOrder2->performers ?? [], JSON_UNESCAPED_UNICODE)."\n";

    if ($updatedOrder2->carrier_id === null) {
        echo "✓ УСПЕХ: carrier_id успешно удален\n";
    } else {
        echo "✗ ОШИБКА: carrier_id не удален: {$updatedOrder2->carrier_id}\n";
    }

    // Тест 3: Проверяем сериализацию после удаления
    echo "\n=== Тест 3: Проверяем сериализацию ===\n";
    $controller = new OrderWizardController;
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('serializeOrder');
    $method->setAccessible(true);

    $serialized = $method->invoke($controller, $updatedOrder2);

    echo "Сериализованные данные:\n";
    echo 'performers[0].contractor_id: '.($serialized['performers'][0]['contractor_id'] ?? 'null')."\n";
    echo 'financial_term.contractors_costs[0].contractor_id: '.($serialized['financial_term']['contractors_costs'][0]['contractor_id'] ?? 'null')."\n";

    if (($serialized['performers'][0]['contractor_id'] ?? null) === null) {
        echo "✓ УСПЕХ: performers правильно сериализованы с null\n";
    } else {
        echo '✗ ОШИБКА: performers не null: '.($serialized['performers'][0]['contractor_id'] ?? 'not set')."\n";
    }

    DB::rollBack(); // Откатываем транзакцию

} catch (Exception $e) {
    DB::rollBack();
    echo "\n✗ ОШИБКА: ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}

echo "\nТест завершен.\n";
