<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\OrderDocumentRequirementService;
use App\Support\OrderTrackReceivedFields;

$order = Order::with(['documents', 'legs.routePoints', 'financialTerms'])->find(141);

if ($order === null) {
    echo "order not found\n";
    exit(1);
}

$req = app(OrderDocumentRequirementService::class);

echo json_encode([
    'order_id' => $order->id,
    'track_received_date_carrier_request' => optional($order->track_received_date_carrier_request)?->toDateString(),
    'track_received_date_carrier_closing' => optional($order->track_received_date_carrier_closing)?->toDateString(),
    'track_received_date_carrier' => optional($order->track_received_date_carrier)?->toDateString(),
    'ottn_resolve' => optional(OrderTrackReceivedFields::resolveForPaymentBasis($order, 'carrier', 'ottn'))?->toDateString(),
    'order_number' => $order->order_number,
    'carrier_payment_form' => $order->carrier_payment_form,
    'customer_payment_form' => $order->customer_payment_form,
    'contractors_costs' => $order->financialTerms->first()?->contractors_costs,
    'payment_schedules' => DB::table('payment_schedules')->where('order_id', 141)->get(),
    'documents' => $order->documents->map(fn ($d) => [
        'id' => $d->id,
        'type' => $d->type,
        'status' => $d->status,
        'party' => data_get($d->metadata, 'party'),
    ]),
    'checklist' => $req->checklistForOrder($order),
    'transport_at' => optional($req->transportDocumentAttachedAt($order))?->toDateString(),
    'payment_package_carrier' => optional($req->paymentPackageAttachedAt($order, 'carrier'))?->toDateString(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
