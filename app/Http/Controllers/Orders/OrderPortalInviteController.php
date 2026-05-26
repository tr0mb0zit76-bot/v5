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
        if (! $this->contractorAssignedOnOrder($order, $contractorId, $request->string('stage')->toString(), $request->carrierSlot())) {
            return response()->json([
                'message' => 'Перевозчик не назначен на выбранное плечо.',
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

    private function contractorAssignedOnOrder(Order $order, int $contractorId, string $stage, int $carrierSlot): bool
    {
        $stage = $this->inviteService->normalizeStageIdentifier($stage);
        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $performerStage = $this->inviteService->normalizeStageIdentifier((string) ($performer['stage'] ?? ''));
            if ($performerStage !== $stage) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $slotNumber = (int) ($slot['slot'] ?? 1);
                    if ($slotNumber === $carrierSlot && (int) ($slot['contractor_id'] ?? 0) === $contractorId) {
                        return true;
                    }
                }

                continue;
            }

            if ((int) ($performer['contractor_id'] ?? 0) === $contractorId) {
                return true;
            }
        }

        return (int) $order->carrier_id === $contractorId && $stage === 'leg_1' && $carrierSlot === 1;
    }
}
