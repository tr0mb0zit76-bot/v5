<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderCarrierPortalInviteRequest;
use App\Models\Order;
use App\Services\OrderPortalInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPortalInviteController extends Controller
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
    ) {}

    public function storeCarrier(
        StoreOrderCarrierPortalInviteRequest $request,
        Order $order,
    ): JsonResponse {
        abort_unless($this->canManageOrder($request, $order), 403);

        $contractorId = $request->integer('contractor_id');
        if (! $this->inviteService->isContractorAssignedOnOrder(
            $order,
            $contractorId,
            $request->string('stage')->toString(),
            $request->carrierSlot(),
        )) {
            return response()->json([
                'message' => 'Перевозчик не назначен на выбранное плечо. Сохраните заказ с выбранным перевозчиком.',
            ], 422);
        }

        $result = $this->inviteService->createCarrierFleetInvite(
            $order,
            $contractorId,
            $request->string('stage')->toString(),
            $request->carrierSlot(),
            $request->user(),
        );

        return response()->json([
            'url' => $result['url'],
            'expires_at' => $result['invite']->expires_at?->toIso8601String(),
            'invite_id' => $result['invite']->id,
        ]);
    }

    private function canManageOrder(Request $request, Order $order): bool
    {
        return app(OrderWizardController::class)->canEditOrder($request, $order);
    }
}
