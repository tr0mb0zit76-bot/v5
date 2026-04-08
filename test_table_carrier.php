<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Contractor;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "=== Тест отображения перевозчика в таблице заказов ===\n\n";

// Получаем существующего пользователя
$user = User::first();
if (! $user) {
    echo "ОШИБКА: Нет пользователей в системе\n";
    exit(1);
}

// Создаем тестового контрагента
$contractor = Contractor::create([
    'name' => 'Тестовый перевозчик для таблицы '.time(),
    'type' => 'carrier',
    'is_active' => true,
    'created_by' => $user->id,
    'updated_by' => $user->id,
]);

echo "1. Создан контрагент: {$contractor->id} - {$contractor->name}\n";

// Создаем заказ с исполнителем
$orderData = [
    'order_number' => 'TABLE-TEST-'.time(),
    'status' => 'new',
    'order_date' => now()->toDateString(),
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => $contractor->id,
            'contractor_name' => $contractor->name,
        ],
    ],
    'client_id' => null,
    'own_company_id' => null,
    'manager_id' => $user->id,
];

$orderWizardService = app(OrderWizardService::class);

echo "2. Создаем заказ с исполнителем...\n";
$order = $orderWizardService->create($orderData, $user);

echo "3. Заказ создан: {$order->id}\n";
echo '   carrier_id в заказе: '.($order->carrier_id ?? 'null')."\n";

// Проверяем, как отображается заказ в таблице
echo "\n4. Проверяем отображение в таблице заказов...\n";
$tableRow = DB::table('orders')
    ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
    ->where('orders.id', $order->id)
    ->select('orders.carrier_id', 'carriers.name as carrier_name')
    ->first();

echo '   carrier_id в таблице: '.($tableRow->carrier_id ?? 'null')."\n";
echo '   carrier_name в таблице: '.($tableRow->carrier_name ?? 'null')."\n";

if ($tableRow->carrier_name === $contractor->name) {
    echo "   ✓ Название перевозчика корректно отображается в таблице\n";
} else {
    echo "   ✗ ОШИБКА: Название перевозчика не отображается в таблице\n";
}

// Теперь удаляем исполнителя
echo "\n5. Удаляем исполнителя...\n";

$updateData = [
    'order_number' => $order->order_number,
    'status' => 'new',
    'order_date' => now()->toDateString(),
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => null,
            'contractor_name' => null,
        ],
    ],
    'client_id' => null,
    'own_company_id' => null,
    'manager_id' => $user->id,
];

$updatedOrder = $orderWizardService->update($order, $updateData, $user);

echo "6. Заказ обновлен\n";
echo '   carrier_id после обновления: '.($updatedOrder->carrier_id ?? 'null')."\n";

// Снова проверяем таблицу
echo "\n7. Проверяем отображение в таблице заказов после удаления...\n";
$tableRowAfter = DB::table('orders')
    ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
    ->where('orders.id', $order->id)
    ->select('orders.carrier_id', 'carriers.name as carrier_name')
    ->first();

echo '   carrier_id в таблице: '.($tableRowAfter->carrier_id ?? 'null')."\n";
echo '   carrier_name в таблице: '.($tableRowAfter->carrier_name ?? 'null')."\n";

if ($tableRowAfter->carrier_id === null && $tableRowAfter->carrier_name === null) {
    echo "   ✓ Название перевозчика корректно удалено из таблицы\n";
} else {
    echo "   ✗ ОШИБКА: Название перевозчика все еще отображается в таблице\n";
    echo '   Причина: carrier_id = '.($tableRowAfter->carrier_id ?? 'null').', carrier_name = '.($tableRowAfter->carrier_name ?? 'null')."\n";
}

// Проверяем напрямую в БД
echo "\n8. Проверяем данные напрямую в БД...\n";
$dbOrder = DB::table('orders')->where('id', $order->id)->first();
echo '   carrier_id в orders: '.($dbOrder->carrier_id ?? 'null')."\n";
echo '   performers в orders: '.($dbOrder->performers ?? '[]')."\n";

echo "\n=== Тест завершен ===\n";

// Удаляем тестовые данные
$order->delete();
$contractor->delete();
