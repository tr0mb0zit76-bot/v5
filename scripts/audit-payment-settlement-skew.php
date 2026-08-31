<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\OrderPartyPaymentSettlementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$roots = DB::table('payment_schedules')
    ->where(function ($q): void {
        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $q->whereNull('parent_payment_id');
        }
    })
    ->when(
        Schema::hasColumn('payment_schedules', 'is_partial'),
        fn ($q) => $q->where(function ($inner): void {
            $inner->whereNull('is_partial')->orWhere('is_partial', false);
        }),
    )
    ->where('status', '!=', 'cancelled')
    ->orderBy('order_id')
    ->orderBy('party')
    ->orderBy('installment_sequence')
    ->get([
        'id', 'order_id', 'party', 'type', 'amount', 'paid_amount', 'remaining_amount', 'status',
        'counterparty_id', 'installment_sequence',
    ]);

$byOrderParty = [];
foreach ($roots as $row) {
    $key = $row->order_id.'|'.strtolower((string) $row->party).'|'.((int) ($row->counterparty_id ?? 0));
    $byOrderParty[$key][] = $row;
}

$skewed = [];
foreach ($byOrderParty as $key => $rows) {
    if (count($rows) < 2) {
        continue;
    }

    $totalAmount = 0.0;
    $totalPaid = 0.0;
    $overpaid = [];
    $unpaidOpen = [];

    foreach ($rows as $row) {
        $amount = round((float) $row->amount, 2);
        $paid = round((float) ($row->paid_amount ?? 0), 2);
        $totalAmount += $amount;
        $totalPaid += $paid;

        if ($paid > $amount + 0.05) {
            $overpaid[] = [
                'id' => (int) $row->id,
                'seq' => $row->installment_sequence,
                'amount' => $amount,
                'paid' => $paid,
            ];
        }

        if ($amount > 0.009 && $paid <= 0.009 && ($row->status ?? '') !== 'paid') {
            $unpaidOpen[] = [
                'id' => (int) $row->id,
                'seq' => $row->installment_sequence,
                'amount' => $amount,
                'status' => $row->status,
            ];
        }
    }

    // Классический баг: один транш переплачен, соседний пустой, а сумма событий покрывает всё.
    if ($overpaid !== [] && $unpaidOpen !== [] && $totalPaid + 0.05 >= $totalAmount) {
        [$orderId, $party] = explode('|', $key);
        $skewed[] = [
            'order_id' => (int) $orderId,
            'party' => $party,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'overpaid' => $overpaid,
            'unpaid_open' => $unpaidOpen,
        ];
    }
}

echo "SKEWED_GROUPS=".count($skewed)."\n";
echo json_encode($skewed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

// Заказы в статусе payment/documents при закрытом документообороте и полной оплате сторон.
$suspectStatuses = DB::table('orders')
    ->whereIn('status', ['payment', 'documents'])
    ->when(Schema::hasColumn('orders', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
    ->orderBy('id')
    ->limit(300)
    ->get(['id', 'status', 'order_number']);

$statusService = app(OrderStatusService::class);
$mismatch = [];

foreach ($suspectStatuses as $row) {
    $order = Order::query()->with(['legs.routePoints', 'documents', 'edoAcknowledgements'])->find($row->id);
    if ($order === null) {
        continue;
    }

    $desc = $statusService->describe($order);
    $derived = $desc['status'];
    if ($derived === (string) $row->status) {
        // дополнительно: stored payment, но customer/carrier по графику уже paid
        if ((string) $row->status === 'payment'
            && $desc['required_documents_completed']
            && $desc['customer_paid']
            && $desc['carrier_paid']
            && $desc['manager_paid']) {
            $mismatch[] = [
                'order_id' => (int) $row->id,
                'order_number' => $row->order_number,
                'stored' => $row->status,
                'derived' => $derived,
                'note' => 'all_paid_but_not_closed',
                'messages' => $desc['messages'],
            ];
        }

        continue;
    }

    $mismatch[] = [
        'order_id' => (int) $row->id,
        'order_number' => $row->order_number,
        'stored' => $row->status,
        'derived' => $derived,
        'customer_paid' => $desc['customer_paid'],
        'carrier_paid' => $desc['carrier_paid'],
        'manager_paid' => $desc['manager_paid'],
        'docs_ok' => $desc['required_documents_completed'],
        'messages' => $desc['messages'],
    ];
}

echo "STATUS_MISMATCH_OR_STUCK=".count($mismatch)."\n";
echo json_encode($mismatch, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
