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

echo "=== Тест сохранения и перезагрузки исполнителя ===\n\n";

// Получаем существующего пользователя
$user = User::first();
if (! $user) {
    echo "ОШИБКА: Нет пользователей в системе\n";
    exit(1);
}

// Создаем двух контрагентов
$contractor1 = Contractor::create([
    'name' => 'Перевозчик 1 '.time(),
    'type' => 'carrier',
    'is_active' => true,
    'created_by' => $user->id,
    'updated_by' => $user->id,
]);

$contractor2 = Contractor::create([
    'name' => 'Перевозчик 2 '.time(),
    'type' => 'carrier',
    'is_active' => true,
    'created_by' => $user->id,
    'updated_by' => $user->id,
]);

echo "1. Созданы контрагенты:\n";
echo "   - {$contractor1->id}: {$contractor1->name}\n";
echo "   - {$contractor2->id}: {$contractor2->name}\n";

// Создаем заказ с первым исполнителем
$orderData = [
    'order_number' => 'RELOAD-TEST-'.time(),
    'status' => 'new',
    'order_date' => now()->toDateString(),
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => $contractor1->id,
            'contractor_name' => $contractor1->name,
        ],
    ],
    'client_id' => null,
    'own_company_id' => null,
    'manager_id' => $user->id,
];

$orderWizardService = app(OrderWizardService::class);

echo "\n2. Создаем заказ с исполнителем 1...\n";
$order = $orderWizardService->create($orderData, $user);

echo "3. Заказ создан: {$order->id}\n";
echo '   carrier_id: '.($order->carrier_id ?? 'null')."\n";
echo '   performers: '.json_encode($order->performers)."\n";

// Проверяем данные в БД
echo "\n4. Проверяем данные в БД после создания:\n";
$dbOrder = DB::table('orders')->where('id', $order->id)->first();
echo '   carrier_id в БД: '.($dbOrder->carrier_id ?? 'null')."\n";
echo '   performers в БД: '.($dbOrder->performers ?? '[]')."\n";

// Теперь меняем исполнителя на второго
echo "\n5. Меняем исполнителя на второго...\n";

$updateData = [
    'order_number' => $order->order_number,
    'status' => 'new',
    'order_date' => now()->toDateString(),
    'performers' => [
        [
            'stage' => 'leg_1',
            'contractor_id' => $contractor2->id,
            'contractor_name' => $contractor2->name,
        ],
    ],
    'client_id' => null,
    'own_company_id' => null,
    'manager_id' => $user->id,
];

$updatedOrder = $orderWizardService->update($order, $updateData, $user);

echo "6. Заказ обновлен\n";
echo '   carrier_id после обновления: '.($updatedOrder->carrier_id ?? 'null')."\n";
echo '   performers после обновления: '.json_encode($updatedOrder->performers)."\n";

// Проверяем данные в БД после обновления
echo "\n7. Проверяем данные в БД после обновления:\n";
$dbOrderAfterUpdate = DB::table('orders')->where('id', $order->id)->first();
echo '   carrier_id в БД: '.($dbOrderAfterUpdate->carrier_id ?? 'null')."\n";
echo '   performers в БД: '.($dbOrderAfterUpdate->performers ?? '[]')."\n";

$performersFromDb = json_decode($dbOrderAfterUpdate->performers ?? '[]', true);
if (is_array($performersFromDb) && count($performersFromDb) > 0) {
    echo '   Первый исполнитель в БД: contractor_id = '.($performersFromDb[0]['contractor_id'] ?? 'null')."\n";
    echo "   Ожидаемый contractor_id: {$contractor2->id}\n";

    if (($performersFromDb[0]['contractor_id'] ?? null) == $contractor2->id) {
        echo "   ✓ Исполнитель корректно обновлен в БД\n";
    } else {
        echo "   ✗ ОШИБКА: Исполнитель не обновлен в БД\n";
    }
}

// Теперь удаляем исполнителя полностью
echo "\n8. Удаляем исполнителя полностью...\n";

$deleteData = [
    'order_number' => $order->order_number,
    'status' => 'new',
    'order_date' => now()->toDateString(),
    'performers' => [], // Пустой массив - удаляем всех исполнителей
    'client_id' => null,
    'own_company_id' => null,
    'manager_id' => $user->id,
];

$orderAfterDelete = $orderWizardService->update($order, $deleteData, $user);

echo "9. Заказ обновлен (исполнитель удален)\n";
echo '   carrier_id после удаления: '.($orderAfterDelete->carrier_id ?? 'null')."\n";
echo '   performers после удаления: '.json_encode($orderAfterDelete->performers)."\n";

// Проверяем данные в БД после удаления
echo "\n10. Проверяем данные в БД после удаления:\n";
$dbOrderAfterDelete = DB::table('orders')->where('id', $order->id)->first();
echo '   carrier_id в БД: '.($dbOrderAfterDelete->carrier_id ?? 'null')."\n";
echo '   performers в БД: '.($dbOrderAfterDelete->performers ?? '[]')."\n";

$performersAfterDelete = json_decode($dbOrderAfterDelete->performers ?? '[]', true);
if (empty($performersAfterDelete)) {
    echo "   ✓ Исполнитель полностью удален из БД\n";
} else {
    echo "   ✗ ОШИБКА: Исполнитель не удален из БД\n";
    echo '   performers: '.json_encode($performersAfterDelete)."\n";
}

// Имитируем F5 - загружаем заказ заново
echo "\n11. Имитируем F5 - загружаем заказ заново...\n";
$reloadedOrder = Order::find($order->id);
echo '   carrier_id при перезагрузке: '.($reloadedOrder->carrier_id ?? 'null')."\n";
echo '   performers при перезагрузке: '.json_encode($reloadedOrder->performers)."\n";

if ($reloadedOrder->carrier_id === null && empty($reloadedOrder->performers)) {
    echo "   ✓ После F5 исполнитель остается удаленным\n";
} else {
    echo "   ✗ ОШИБКА: После F5 исполнитель вернулся!\n";
}

echo "\n=== Тест завершен ===\n";

// Удаляем тестовые данные
$order->delete();
$contractor1->delete();
$contractor2->delete();
