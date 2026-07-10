<?php

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Support\PaymentScheduleAutomaticStatus;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$orderId = 5;
$links = [
    45 => 8105,
    33 => 8106,
    56 => 8106,
];

foreach ($links as $eventId => $scheduleId) {
    $updated = DB::table('payment_schedule_payment_events')
        ->where('id', $eventId)
        ->where('order_id', $orderId)
        ->whereNull('reversed_at')
        ->update([
            'payment_schedule_id' => $scheduleId,
            'updated_at' => now(),
        ]);
    echo "Link event #{$eventId} -> sched #{$scheduleId}: {$updated}\n";
}

$sync = app(PaymentScheduleSettlementSyncService::class);
$updated = 0;
foreach (PaymentSchedule::query()->where('order_id', $orderId)->whereNull('parent_payment_id')->get() as $schedule) {
    if ($sync->syncRootSchedule($schedule)) {
        $updated++;
    }
}
echo "Synced roots: {$updated}\n";

PaymentScheduleAutomaticStatus::refreshForOrder($orderId);

echo PHP_EOL.'=== AFTER ==='.PHP_EOL;
$activeCustomerSum = PaymentSchedulePaymentEvent::query()
    ->active()
    ->where('order_id', $orderId)
    ->where('party', 'customer')
    ->sum('amount');
echo 'active customer ledger sum: '.$activeCustomerSum.PHP_EOL;

foreach (PaymentSchedule::query()->where('order_id', $orderId)->where('party', 'customer')->orderBy('installment_sequence')->get() as $s) {
    echo 'sched#'.$s->id.' seq='.($s->installment_sequence ?? '-').' amount='.$s->amount.' paid='.$s->paid_amount.' remain='.$s->remaining_amount.' status='.$s->status.PHP_EOL;
}
