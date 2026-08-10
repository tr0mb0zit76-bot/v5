<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OneC\OneCRealizationSyncService;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderOneCRealizationController extends Controller
{
    public function __construct(
        private readonly OneCRealizationSyncService $sync,
    ) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless(RoleAccess::canCreateOneCRealization($user), 403);
        abort_unless(OrderViewAuthorization::userCanMutateOrder($user, $order), 403);

        $validated = $request->validate([
            'force' => ['sometimes', 'boolean'],
        ]);

        $result = $this->sync->createForOrder(
            $order,
            $user,
            (bool) ($validated['force'] ?? false),
        );

        return response()->json([
            'created' => $result['created'],
            'realization' => $result['document']->toWizardSummary(),
            'one_c' => $this->sync->wizardState($order, $user),
        ]);
    }
}
