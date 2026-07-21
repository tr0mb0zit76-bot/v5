<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderLinkRequest;
use App\Models\Order;
use App\Services\Orders\OrderLinkService;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderLinkController extends Controller
{
    public function __construct(
        private readonly OrderLinkService $orderLinks,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && RoleAccess::canAccessVisibilityArea($user, 'orders'), 403);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'exclude_order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $results = $this->orderLinks->searchLinkCandidates(
            $user,
            (string) $validated['q'],
            isset($validated['exclude_order_id']) ? (int) $validated['exclude_order_id'] : null,
        );

        return response()->json(['data' => $results]);
    }

    public function store(StoreOrderLinkRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && OrderViewAuthorization::userCanMutateOrder($user, $order), 403);

        $peer = Order::query()->findOrFail((int) $request->validated('linked_order_id'));
        abort_unless(OrderViewAuthorization::userCanViewOrder($user, $peer), 403);

        try {
            $this->orderLinks->link($order, $peer, $user);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'linked_order' => $this->orderLinks->linkedOrderPayload($order->fresh()),
        ]);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && OrderViewAuthorization::userCanMutateOrder($user, $order), 403);

        $this->orderLinks->unlink($order);

        return response()->json(['linked_order' => null]);
    }
}
