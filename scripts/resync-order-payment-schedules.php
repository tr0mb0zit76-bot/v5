<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\OrderCompensationService;

$orderId = (int) ($argv[1] ?? 0);

if ($orderId <= 0) {
    fwrite(STDERR, "Usage: php scripts/resync-order-payment-schedules.php {order_id}\n");
    exit(1);
}

$order = Order::with(['documents', 'financialTerms', 'legs.routePoints', 'edoAcknowledgements'])->find($orderId);

if ($order === null) {
    fwrite(STDERR, "Order {$orderId} not found\n");
    exit(1);
}

app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order);

$rows = DB::table('payment_schedules')
    ->where('order_id', $orderId)
    ->orderBy('party')
    ->get(['id', 'party', 'planned_date', 'status']);

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
