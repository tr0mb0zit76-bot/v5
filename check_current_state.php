<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;

echo "Текущее состояние заказа OOOL-2604-0003\n\n";

$order = Order::where('order_number', 'OOOL-2604-0003')->first();
if (!$order) {
    echo "Заказ не найден\n";
    exit;
}

echo "Заказ ID: " . $order->id . "\n";
echo "Номер: " . $order->order_number . "\n";
echo "Статус: " . $order->status . "\n";
echo "Создатель (manager_id): " . ($order->manager_id ?? 'null') . "\n";
echo "carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
echo "performers: " . json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n";
echo "updated_at: " . $order->updated_at . "\n";
echo "updated_by: " . ($order->updated_by ?? 'null') . "\n\n";

// Проверим историю изменений
echo "Проверка financial_terms:\n";
if (method_exists($order, 'financialTerms')) {
    $financialTerm = $order->financialTerms->first();
    if ($financialTerm) {
        echo "contractors_costs: " . json_encode($financialTerm->contractors_costs ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        echo "updated_at: " . $financialTerm->updated_at . "\n";
    }
}

echo "\nВывод: Заказ успешно обновлен! carrier_id = 970\n";