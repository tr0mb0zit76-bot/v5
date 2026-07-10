<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderClipboardSummaryResolver;
use App\Support\OrderViewAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTransportSummaryController extends Controller
{
    public function __invoke(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        abort_unless(OrderViewAuthorization::userCanViewOrder($user, $order), 403);

        $summaries = app(OrderClipboardSummaryResolver::class)->mapForOrders(collect([$order]));

        return response()->json([
            'summary' => $summaries[(int) $order->id] ?? '',
        ]);
    }
}
