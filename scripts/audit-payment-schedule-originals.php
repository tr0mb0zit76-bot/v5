<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Services\OrderCompensationService;
use Illuminate\Support\Facades\DB;

$withPlanned = PaymentSchedule::query()
    ->whereNotNull('planned_date')
    ->whereIn('status', ['pending', 'overdue'])
    ->count();

echo "Open rows with planned_date: {$withPlanned}\n";

$cleared = 0;
$changed = 0;
$sample = [];

PaymentSchedule::query()
    ->whereNotNull('planned_date')
    ->whereIn('status', ['pending', 'overdue'])
    ->orderByDesc('id')
    ->limit(300)
    ->get()
    ->each(function (PaymentSchedule $row) use (&$cleared, &$changed, &$sample): void {
        $order = Order::with(['documents', 'financialTerms', 'legs.routePoints', 'edoAcknowledgements'])->find($row->order_id);
        if ($order === null) {
            return;
        }

        $before = $row->planned_date?->toDateString();
        $psId = (int) $row->id;

        DB::beginTransaction();

        try {
            app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order);

            $afterRow = PaymentSchedule::query()
                ->where('order_id', $row->order_id)
                ->where('party', $row->party)
                ->when(
                    $row->counterparty_id !== null,
                    fn ($query) => $query->where('counterparty_id', $row->counterparty_id),
                )
                ->when(
                    $row->installment_sequence !== null,
                    fn ($query) => $query->where('installment_sequence', $row->installment_sequence),
                )
                ->first();

            $after = $afterRow?->planned_date?->toDateString();

            if ($before !== $after) {
                if ($after === null) {
                    $cleared++;
                } else {
                    $changed++;
                }

                if (count($sample) < 20) {
                    $sample[] = [
                        'order_id' => (int) $row->order_id,
                        'ps_id' => $psId,
                        'party' => $row->party,
                        'before' => $before,
                        'after' => $after,
                    ];
                }
            }
        } finally {
            DB::rollBack();
        }
    });

echo "Dry-run on last 300 open dated rows: would_clear={$cleared}, would_change={$changed}\n";
echo json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
