<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderClipboardSummaryResolver;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTransportSummaryController extends Controller
{
    public function __invoke(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');
        if (! RoleAccess::isAdminUser($user) && $scope !== 'all' && (int) $order->manager_id !== (int) $user->id) {
            abort(403);
        }

        $summaries = app(OrderClipboardSummaryResolver::class)->mapForOrders(collect([$order]));

        return response()->json([
            'summary' => $summaries[(int) $order->id] ?? '',
        ]);
    }
}
