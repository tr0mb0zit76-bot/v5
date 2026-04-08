<?php

// Тестируем, что сервер отправляет на фронтенд
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Contractor;

// Создаем тестовые данные
$carrier = Contractor::factory()->create(['type' => 'carrier']);
$order = Order::factory()->create([
    'carrier_id' => $carrier->id,
    'performers' => json_encode([[
        'stage' => 'leg_1',
        'contractor_id' => $carrier->id,
    ]]),
]);

echo "Заказ создан:\n";
echo "ID: " . $order->id . "\n";
echo "carrier_id: " . $order->carrier_id . "\n";
echo "performers: " . $order->performers . "\n";

// Проверяем, что возвращает метод loadOrderForEditing
$controller = new App\Http\Controllers\Orders\OrderWizardController();
$loadedOrder = $controller->loadOrderForEditing($order);

echo "\n\nЗагруженный заказ для редактирования:\n";
echo "performers: " . json_encode($loadedOrder->performers ?? []) . "\n";

// Проверяем, есть ли поле carriers
echo "\n\nПроверяем наличие поля 'carriers':\n";
if (isset($loadedOrder->carriers)) {
    echo "Поле 'carriers' присутствует: " . json_encode($loadedOrder->carriers) . "\n";
} else {
    echo "Поле 'carriers' отсутствует\n";
}

// Проверяем структуру данных
echo "\n\nСтруктура данных заказа:\n";
$data = $loadedOrder->toArray();
foreach ($data as $key => $value) {
    if ($key === 'performers' || $key === 'carriers') {
        echo "$key: " . json_encode($value) . "\n";
    }
}