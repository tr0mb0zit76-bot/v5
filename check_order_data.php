<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;

echo "Проверка данных заказа в базе\n\n";

// Найдем все заказы
$orders = Order::limit(5)->get();

foreach ($orders as $order) {
    echo "Заказ ID: " . $order->id . "\n";
    echo "Номер: " . $order->order_number . "\n";
    echo "carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
    
    $performers = $order->performers ?? [];
    if (!empty($performers)) {
        echo "performers: " . json_encode($performers, JSON_UNESCAPED_UNICODE) . "\n";
        if (isset($performers[0]['contractor_id'])) {
            echo "performers[0].contractor_id: " . $performers[0]['contractor_id'] . "\n";
        }
    } else {
        echo "performers: пусто\n";
    }
    
    echo "---\n";
}

// Проверим конкретный заказ по номеру из логов
echo "\nПоиск заказа OOOL-2604-0003:\n";
$specificOrder = Order::where('order_number', 'OOOL-2604-0003')->first();
if ($specificOrder) {
    echo "Найден заказ ID: " . $specificOrder->id . "\n";
    echo "carrier_id: " . ($specificOrder->carrier_id ?? 'null') . "\n";
    echo "performers: " . json_encode($specificOrder->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Проверим financial_terms если есть
    if (method_exists($specificOrder, 'financialTerms')) {
        $financialTerm = $specificOrder->financialTerms->first();
        if ($financialTerm) {
            echo "financial_terms.contractors_costs: " . json_encode($financialTerm->contractors_costs ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo "Заказ OOOL-2604-0003 не найден\n";
}