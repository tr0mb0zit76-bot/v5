<?php

declare(strict_types=1);
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = '/var/www/www-root/data/www/avtoaliyans.ru';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$orderId = (int) ($argv[1] ?? 5);

$order = Order::query()->find($orderId);

if ($order === null) {
    echo "Order {$orderId} not found\n";
    exit(1);
}

$paidTotal = round((float) DB::table('salary_accruals')
    ->where('order_id', $orderId)
    ->sum('paid_amount_fact'), 2);

DB::table('orders')
    ->where('id', $orderId)
    ->update(['salary_paid' => $paidTotal]);

$service = app(OrderStatusService::class);
$status = $service->syncStoredStatus($order->fresh());

$desc = $service->describe($order->fresh());

echo json_encode([
    'order_id' => $orderId,
    'salary_paid' => $paidTotal,
    'status' => $status,
    'manager_paid' => $desc['manager_paid'],
    'customer_paid' => $desc['customer_paid'],
    'carrier_paid' => $desc['carrier_paid'],
    'messages' => $desc['messages'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
