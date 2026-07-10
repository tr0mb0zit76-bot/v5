<?php

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Services\Finance\PaymentSchedulePaymentReversalService;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentSchedulePaymentEventRelinker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$orderId = 5;
$duplicateEventIds = [32, 34];
$reason = 'Дубль ручной оплаты 50 000 ₽; канон — mgmt:9 (событие #56)';

$actor = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
    ?? User::query()->orderBy('id')->first();

if ($actor === null) {
    echo "No user for reversal actor\n";
    exit(1);
}

$reversal = app(PaymentSchedulePaymentReversalService::class);

foreach ($duplicateEventIds as $eventId) {
    $event = PaymentSchedulePaymentEvent::query()->find($eventId);
    if ($event === null) {
        echo "Event #{$eventId} not found\n";

        continue;
    }
    if ($event->reversed_at !== null) {
        echo "Event #{$eventId} already reversed\n";

        continue;
    }
    if ((int) $event->order_id !== $orderId) {
        echo "Event #{$eventId} wrong order\n";
        exit(1);
    }
    $reversal->reverseEvent($event, $actor, $reason);
    echo "Reversed event #{$eventId}\n";
}

$relinker = app(PaymentSchedulePaymentEventRelinker::class);
$sync = app(PaymentScheduleSettlementSyncService::class);

$relinked = $relinker->relinkOrphanedEventsForOrder($orderId);
echo "Relinked: {$relinked}\n";

$updated = 0;
$query = PaymentSchedule::query()->where('order_id', $orderId)->whereNull('parent_payment_id');
if (Schema::hasColumn('payment_schedules', 'is_partial')) {
    $query->where(fn ($q) => $q->whereNull('is_partial')->orWhere('is_partial', false));
}
foreach ($query->get() as $schedule) {
    if ($sync->syncRootSchedule($schedule)) {
        $updated++;
    }
}
echo "Synced roots: {$updated}\n";

PaymentScheduleAutomaticStatus::refreshForOrder($orderId);

echo PHP_EOL.'=== AFTER ==='.PHP_EOL;
$order = Order::find($orderId);
echo 'customer_rate='.($order->customer_rate ?? '-').PHP_EOL;

$activeCustomerSum = PaymentSchedulePaymentEvent::query()
    ->active()
    ->where('order_id', $orderId)
    ->where('party', 'customer')
    ->sum('amount');
echo 'active customer ledger sum: '.$activeCustomerSum.PHP_EOL;

foreach (PaymentSchedule::query()->where('order_id', $orderId)->where('party', 'customer')->orderBy('installment_sequence')->get() as $s) {
    echo 'sched#'.$s->id.' seq='.($s->installment_sequence ?? '-').' amount='.$s->amount.' paid='.$s->paid_amount.' remain='.$s->remaining_amount.' status='.$s->status.PHP_EOL;
}
