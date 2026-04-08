<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;

echo "Прямое обновление заказа\n\n";

// Найдем заказ
$order = Order::where('order_number', 'OOOL-2604-0003')->first();
if (!$order) {
    echo "Заказ не найден\n";
    exit;
}

echo "До обновления:\n";
echo "ID: " . $order->id . "\n";
echo "carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
echo "performers: " . json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";

// Прямое обновление
echo "Обновляем carrier_id на 970...\n";
$order->carrier_id = 970;
$order->performers = [['stage' => 'Плечо 1', 'contractor_id' => 970]];

try {
    $saved = $order->save();
    
    if ($saved) {
        echo "✓ Заказ сохранен\n";
        
        // Перезагружаем из базы
        $order->refresh();
        
        echo "\nПосле обновления:\n";
        echo "carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
        echo "performers: " . json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($order->carrier_id == 970) {
            echo "\n✓ Успех! carrier_id обновлен на 970\n";
        } else {
            echo "\n✗ Ошибка: carrier_id не обновлен\n";
        }
    } else {
        echo "✗ Ошибка сохранения\n";
    }
} catch (Exception $e) {
    echo "✗ Исключение: " . $e->getMessage() . "\n";
}

// Проверим financial_terms
echo "\nПроверка financial_terms:\n";
if (method_exists($order, 'financialTerms')) {
    $financialTerm = $order->financialTerms->first();
    if ($financialTerm) {
        echo "contractors_costs: " . json_encode($financialTerm->contractors_costs ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        
        // Обновим contractors_costs
        if (is_array($financialTerm->contractors_costs) && !empty($financialTerm->contractors_costs)) {
            $financialTerm->contractors_costs[0]['contractor_id'] = 970;
            $financialTerm->save();
            echo "✓ contractors_costs обновлен\n";
        }
    }
}

echo "\nПроверка завершена.\n";