<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Http\Controllers\Orders\OrderWizardController;

$order = Order::first();
if (!$order) {
    echo "No orders found\n";
    exit;
}

$controller = new OrderWizardController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('serializeOrder');
$method->setAccessible(true);

$serialized = $method->invoke($controller, $order);

echo "Serialized order data:\n";
echo json_encode($serialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Check if carriers field exists
if (isset($serialized['carriers'])) {
    echo "\n\nWARNING: 'carriers' field found in serialized data!\n";
} else {
    echo "\n\nOK: 'carriers' field NOT found in serialized data.\n";
}

// Check performers field
if (isset($serialized['performers'])) {
    echo "OK: 'performers' field found with " . count($serialized['performers']) . " items.\n";
} else {
    echo "WARNING: 'performers' field NOT found!\n";
}