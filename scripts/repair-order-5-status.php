<?php

declare(strict_types=1);
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Services\OrderStatusService;
use App\Support\PaymentScheduleAutomaticStatus;
use Illuminate\Contracts\Console\Kernel;

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

$staleIds = PaymentSchedule::query()
    ->where('order_id', $orderId)
    ->whereIn('id', [9643, 9644, 9645, 9646])
    ->pluck('id')
    ->all();

if ($staleIds !== []) {
    PaymentSchedule::query()
        ->whereIn('id', $staleIds)
        ->update(['status' => 'cancelled', 'updated_at' => now()]);
    echo 'cancelled_stale='.implode(',', $staleIds).PHP_EOL;
}

$customerFinal = PaymentSchedule::query()->find(9648);

if ($customerFinal !== null && $customerFinal->order_id === $orderId && $customerFinal->status !== 'paid') {
    $amount = round((float) $customerFinal->amount, 2);
    $customerFinal->forceFill([
        'paid_amount' => $amount,
        'remaining_amount' => 0,
        'status' => 'paid',
    ])->save();
    echo 'fixed_customer_final=9648'.PHP_EOL;
}

PaymentScheduleAutomaticStatus::refreshForOrder($orderId);

$order = $order->fresh(['legs.routePoints', 'documents', 'edoAcknowledgements']);
$describeBefore = app(OrderStatusService::class)->describe($order);
echo 'before='.json_encode($describeBefore, JSON_UNESCAPED_UNICODE).PHP_EOL;

$newStatus = app(OrderStatusService::class)->syncStoredStatus($order);
echo 'status_after='.$newStatus.PHP_EOL;
