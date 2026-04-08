<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use Inertia\Inertia;
use App\Http\Controllers\Orders\OrderWizardController;

$order = Order::first();
if (!$order) {
    echo "No orders found\n";
    exit;
}

$controller = new OrderWizardController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('loadOrderForEditing');
$method->setAccessible(true);

$loadedOrder = $method->invoke($controller, $order);

echo "Loaded order (with relations):\n";
echo json_encode($loadedOrder->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Check for carriers relation
if ($loadedOrder->relationLoaded('carrier')) {
    echo "\n\nWARNING: 'carrier' relation is loaded!\n";
    if (isset($loadedOrder->carrier)) {
        echo "Carrier data: " . json_encode($loadedOrder->carrier->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Check for any computed attributes
echo "\n\nAll attributes on loaded order:\n";
foreach ($loadedOrder->getAttributes() as $key => $value) {
    echo "- $key\n";
}

// Check for appended attributes
if (method_exists($loadedOrder, 'getAppends')) {
    $appends = $loadedOrder->getAppends();
    if (!empty($appends)) {
        echo "\n\nAppended attributes:\n";
        foreach ($appends as $append) {
            echo "- $append\n";
        }
    }
}