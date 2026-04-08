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

echo "=== Тест метода normalizeContractorsCosts ===\n\n";

// Получаем существующего пользователя
$user = User::first();
if (! $user) {
    echo "ОШИБКА: Нет пользователей в системе\n";
    exit(1);
}

// Создаем тестового контрагента
$contractor = Contractor::create([
    'name' => 'Тестовый перевозчик для normalize '.time(),
    'type' => 'carrier',
    'is_active' => true,
    'created_by' => $user->id,
    'updated_by' => $user->id,
]);

echo "1. Создан контрагент: {$contractor->id} - {$contractor->name}\n";

// Создаем заказ с исполнителем
$orderData = [
    'order_number' => 'NORMALIZE-TEST-'.time(),
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

echo "\n2. Создаем заказ с исполнителем...\n";
$order = $orderWizardService->create($orderData, $user);

echo "3. Заказ создан: {$order->id}\n";
echo '   carrier_id: '.($order->carrier_id ?? 'null')."\n";
echo '   performers: '.json_encode($order->performers)."\n";

// Создаем экземпляр контроллера
$controller = new OrderWizardController;

// Тестируем метод normalizeContractorsCosts
echo "\n4. Тестируем метод normalizeContractorsCosts...\n";

// Получаем financial term
$financialTerm = $order->financialTerms->first();

// Вызываем метод через Reflection, так как он private
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('normalizeContractorsCosts');
$method->setAccessible(true);

$result = $method->invoke($controller, $order, $financialTerm);

echo "   Результат normalizeContractorsCosts:\n";
foreach ($result as $index => $cost) {
    echo "   [$index] stage: {$cost['stage']}, contractor_id: ".($cost['contractor_id'] ?? 'null')."\n";
}

// Теперь удаляем исполнителя
echo "\n5. Удаляем исполнителя...\n";

$updateData = [
    'order_number' => $order->order_number,
    'status' => 'new',
    'order_date' => now()->toDateString(),
    'performers' => [], // Пустой массив
    'client_id' => null,
    'own_company_id' => null,
    'manager_id' => $user->id,
];

$updatedOrder = $orderWizardService->update($order, $updateData, $user);

echo "6. Заказ обновлен\n";
echo '   carrier_id после удаления: '.($updatedOrder->carrier_id ?? 'null')."\n";
echo '   performers после удаления: '.json_encode($updatedOrder->performers)."\n";

// Проверяем данные в БД
echo "\n7. Проверяем данные в БД...\n";
$dbOrder = DB::table('orders')->where('id', $order->id)->first();
echo '   carrier_id в БД: '.($dbOrder->carrier_id ?? 'null')."\n";
echo '   performers в БД: '.($dbOrder->performers ?? '[]')."\n";

// Загружаем заказ заново с отношениями
echo "\n8. Загружаем заказ заново с отношениями...\n";
$reloadedOrder = Order::with('financialTerms')->find($order->id);
echo '   carrier_id при перезагрузке: '.($reloadedOrder->carrier_id ?? 'null')."\n";
echo '   performers при перезагрузке: '.json_encode($reloadedOrder->performers)."\n";

// Снова тестируем метод normalizeContractorsCosts
echo "\n9. Снова тестируем метод normalizeContractorsCosts...\n";
$financialTermAfter = $reloadedOrder->financialTerms->first();
$resultAfter = $method->invoke($controller, $reloadedOrder, $financialTermAfter);

echo "   Результат normalizeContractorsCosts после удаления:\n";
foreach ($resultAfter as $index => $cost) {
    echo "   [$index] stage: {$cost['stage']}, contractor_id: ".($cost['contractor_id'] ?? 'null')."\n";
}

// Проверяем, что contractor_id не восстановился из carrier_id
if (empty($resultAfter) || (isset($resultAfter[0]['contractor_id']) && $resultAfter[0]['contractor_id'] === null)) {
    echo "   ✓ contractor_id корректно установлен в null\n";
} else {
    echo '   ✗ ОШИБКА: contractor_id восстановился из carrier_id: '.($resultAfter[0]['contractor_id'] ?? 'null')."\n";
    echo '   Примечание: carrier_id в заказе: '.($reloadedOrder->carrier_id ?? 'null')."\n";
}

// Проверяем условие в методе normalizeContractorsCosts
echo "\n10. Проверяем условие в методе normalizeContractorsCosts:\n";
echo '   performers пустой? '.(empty($reloadedOrder->performers) ? 'да' : 'нет')."\n";
echo '   contractors_costs пустой? '.(empty($financialTermAfter?->contractors_costs) ? 'да' : 'нет')."\n";
echo '   carrier_id не null? '.($reloadedOrder->carrier_id !== null ? 'да' : 'нет')."\n";
echo '   carrier_rate не null? '.($reloadedOrder->carrier_rate !== null ? 'да' : 'нет')."\n";

$condition = empty($reloadedOrder->performers) &&
             empty($financialTermAfter?->contractors_costs) &&
             ($reloadedOrder->carrier_id !== null || $reloadedOrder->carrier_rate !== null);

echo '   Условие для восстановления исполнителя: '.($condition ? 'ВЫПОЛНЕНО' : 'не выполнено')."\n";

echo "\n=== Тест завершен ===\n";

// Удаляем тестовые данные
$order->delete();
$contractor->delete();
