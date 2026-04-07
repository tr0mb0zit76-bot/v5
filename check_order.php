<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

echo "Checking order OOOA-2604-0006...\n";

$order = Order::where('order_number', 'OOOA-2604-0006')->first();

if (!$order) {
    echo "Order not found\n";
    exit(1);
}

echo "Current carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
echo "Performers: " . json_encode($order->performers, JSON_PRETTY_PRINT) . "\n";

// Проверим историю изменений
echo "\nChecking order history...\n";
echo "Created at: " . $order->created_at . "\n";
echo "Updated at: " . $order->updated_at . "\n";
echo "Updated by: " . $order->updated_by . "\n";

// Проверим, есть ли триггеры или события
echo "\nChecking for model events...\n";
$events = ['saving', 'saved', 'updating', 'updated', 'creating', 'created'];
foreach ($events as $event) {
    if (method_exists($order, 'getEventDispatcher') && $order->getEventDispatcher()->hasListeners("eloquent.{$event}: " . get_class($order))) {
        echo "Event '{$event}' has listeners\n";
    }
}

// Проверим структуру таблицы
echo "\nChecking table structure...\n";
$columns = \Illuminate\Support\Facades\Schema::getColumnListing('orders');
echo "Columns in orders table: " . implode(', ', $columns) . "\n";

// Проверим default значение для carrier_id
$columnInfo = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM orders WHERE Field = 'carrier_id'");
if (!empty($columnInfo)) {
    $info = (array)$columnInfo[0];
    echo "carrier_id column info:\n";
    echo "  Type: " . $info['Type'] . "\n";
    echo "  Null: " . $info['Null'] . "\n";
    echo "  Default: " . ($info['Default'] ?? 'NULL') . "\n";
}