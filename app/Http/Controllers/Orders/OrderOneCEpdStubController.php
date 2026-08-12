<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Services\OneC\OneCEpdStubSyncService;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderOneCEpdStubController extends Controller
{
    public function __construct(
        private readonly OneCEpdStubSyncService $sync,
    ) {}

    public function storeEtrn(Request $request, Order $order): JsonResponse
    {
        return $this->storeWithType($request, $order, OrderOneCDocument::TYPE_ETRN);
    }

    public function storeExpeditionReceipt(Request $request, Order $order): JsonResponse
    {
        return $this->storeWithType($request, $order, OrderOneCDocument::TYPE_EXPEDITION_RECEIPT);
    }

    private function storeWithType(Request $request, Order $order, string $documentType): JsonResponse
    {
        $user = $request->user();
        abort_unless(RoleAccess::canCreateOneCRealization($user), 403);
        abort_unless(OrderViewAuthorization::userCanMutateOrder($user, $order), 403);

        $result = $this->sync->pushForOrder($order, $user, $documentType);

        return response()->json([
            'action' => $result['action'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'document' => $result['document']->toWizardSummary(),
            'epd' => $this->sync->wizardStates($order, $user),
        ]);
    }
}
