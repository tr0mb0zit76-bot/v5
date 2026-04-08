<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Contractor;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "=== Тест полного цикла сохранения и загрузки исполнителя ===\n\n";

// Получаем существующего пользователя
$user = User::first();
if (! $user) {
    echo "ОШИБКА: Нет пользователей в системе\n";
    exit(1);
}

// Создаем тестового контрагента напрямую
$contractor = Contractor::create([
    'name' => 'Тестовый перевозчик '.time(),
    'type' => 'carrier',
    'is_active' => true,
    'created_by' => $user->id,
    'updated_by' => $user->id,
]);

echo "1. Создан контрагент: {$contractor->id} - {$contractor->name}\n";

// Создаем заказ с исполнителем
$orderData = [
    'order_number' => 'TEST-'.time(),
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
    'manager_id' => 1,
];

$orderWizardService = app(OrderWizardService::class);
$user = User::first(); // Получаем первого пользователя

echo "2. Создаем заказ с исполнителем...\n";
$order = $orderWizardService->create($orderData, $user);

echo "3. Заказ создан: {$order->id}\n";
echo '   carrier_id в заказе: '.($order->carrier_id ?? 'null')."\n";
echo '   performers в заказе: '.json_encode($order->performers)."\n";

// Проверяем, что carrier_id установлен
if ($order->carrier_id === $contractor->id) {
    echo "   ✓ carrier_id корректно установлен\n";
} else {
    echo "   ✗ ОШИБКА: carrier_id не установлен\n";
}

// Теперь удаляем исполнителя (устанавливаем contractor_id в null)
echo "\n4. Удаляем исполнителя (устанавливаем contractor_id в null)...\n";

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
    'manager_id' => 1,
];

$updatedOrder = $orderWizardService->update($order, $updateData, $user);

echo "5. Заказ обновлен\n";
echo '   carrier_id после обновления: '.($updatedOrder->carrier_id ?? 'null')."\n";
echo '   performers после обновления: '.json_encode($updatedOrder->performers)."\n";

// Проверяем, что carrier_id стал null
if ($updatedOrder->carrier_id === null) {
    echo "   ✓ carrier_id корректно установлен в null\n";
} else {
    echo "   ✗ ОШИБКА: carrier_id не стал null\n";
}

// Теперь загружаем заказ заново (имитируем обновление страницы)
echo "\n6. Загружаем заказ заново (имитация обновления страницы)...\n";

$reloadedOrder = Order::find($order->id);
echo '   carrier_id при повторной загрузке: '.($reloadedOrder->carrier_id ?? 'null')."\n";
echo '   performers при повторной загрузке: '.json_encode($reloadedOrder->performers)."\n";

if ($reloadedOrder->carrier_id === null) {
    echo "   ✓ carrier_id остается null после перезагрузки\n";
} else {
    echo "   ✗ ОШИБКА: carrier_id не null после перезагрузки\n";
}

// Проверяем данные в БД напрямую
echo "\n7. Проверяем данные напрямую в БД...\n";
$dbOrder = DB::table('orders')->where('id', $order->id)->first();
echo '   carrier_id в БД: '.($dbOrder->carrier_id ?? 'null')."\n";
echo '   performers в БД: '.($dbOrder->performers ?? '[]')."\n";

$performersFromDb = json_decode($dbOrder->performers ?? '[]', true);
if (is_array($performersFromDb) && count($performersFromDb) > 0) {
    echo '   Первый исполнитель в БД: contractor_id = '.($performersFromDb[0]['contractor_id'] ?? 'null')."\n";
}

echo "\n=== Тест завершен ===\n";

// Удаляем тестовые данные
$order->delete();
$contractor->delete();
