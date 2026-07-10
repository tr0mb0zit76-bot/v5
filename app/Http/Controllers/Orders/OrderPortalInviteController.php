<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderCarrierPortalInviteRequest;
use App\Models\Order;
use App\Services\OrderPortalInviteAccessService;
use App\Services\OrderPortalInviteService;
use App\Services\Orders\Wizard\OrderWizardOrderAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPortalInviteController extends Controller
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
        private readonly OrderWizardOrderAuthorization $orderAuthorization,
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
            'link_validity_hint' => app(OrderPortalInviteAccessService::class)->linkValidityHint(),
            'invite_id' => $result['invite']->id,
        ]);
    }

    public function storeCustomer(Request $request, Order $order): JsonResponse
    {
        abort_unless($this->canManageOrder($request, $order), 403);

        if ((int) $order->customer_id <= 0) {
            return response()->json([
                'message' => 'Сначала укажите заказчика в заказе.',
            ], 422);
        }

        try {
            $result = $this->inviteService->createCustomerDocumentsInvite(
                $order,
                $request->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'url' => $result['url'],
            'link_validity_hint' => app(OrderPortalInviteAccessService::class)->linkValidityHint(),
            'invite_id' => $result['invite']->id,
        ]);
    }

    private function canManageOrder(Request $request, Order $order): bool
    {
        return $this->orderAuthorization->canEditOrder($request, $order);
    }
}
