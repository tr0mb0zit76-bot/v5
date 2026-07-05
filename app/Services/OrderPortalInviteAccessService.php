<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Support\PerformerRouteActualDates;
use Illuminate\Support\Facades\Schema;

final class OrderPortalInviteAccessService
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
    ) {}

    public function isClosedBecauseUnloadingComplete(Order $order, OrderPortalInvite $invite): bool
    {
        return $this->unloadingActualForInvite($order, $invite) !== null;
    }

    public function isInviteClosed(Order $order, OrderPortalInvite $invite): bool
    {
        if ($invite->isRevoked()) {
            return true;
        }

        if ($this->isClosedBecauseUnloadingComplete($order, $invite)) {
            return true;
        }

        return $invite->expires_at !== null && $invite->expires_at->isPast();
    }

    public function canUploadDocuments(Order $order, OrderPortalInvite $invite): bool
    {
        return ! $this->isInviteClosed($order, $invite);
    }

    public function canSubmitFleetForm(Order $order, OrderPortalInvite $invite): bool
    {
        return $this->canUploadDocuments($order, $invite) && ! $invite->isSubmitted();
    }

    public function linkValidityHint(): string
    {
        return 'Ссылка действует до проставления фактической даты выгрузки по заказу.';
    }

    public function unloadingActualForInvite(Order $order, OrderPortalInvite $invite): ?string
    {
        if ($invite->purpose === OrderPortalInvite::PURPOSE_CUSTOMER_DOCUMENTS) {
            return $this->unloadingActualForCustomerOrder($order);
        }

        $stage = $this->inviteService->normalizeStageIdentifier($invite->stage);
        $carrierSlot = max(1, (int) $invite->carrier_slot);
        $contractorId = (int) $invite->contractor_id;

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

                    $slotNumber = max(1, (int) ($slot['slot'] ?? 1));
                    if ($slotNumber !== $carrierSlot) {
                        continue;
                    }

                    if ((int) ($slot['contractor_id'] ?? 0) !== $contractorId) {
                        continue;
                    }

                    $unloading = PerformerRouteActualDates::normalizeDate($slot['unloading_actual'] ?? null);
                    if ($unloading !== null) {
                        return $unloading;
                    }
                }

                continue;
            }

            if ((int) ($performer['contractor_id'] ?? 0) !== $contractorId) {
                continue;
            }

            $unloading = PerformerRouteActualDates::normalizeDate($performer['unloading_actual'] ?? null);
            if ($unloading !== null) {
                return $unloading;
            }
        }

        if ($order->relationLoaded('legs') || Schema::hasTable('order_legs')) {
            $order->loadMissing(['legs.routePoints' => fn ($q) => $q->orderBy('sequence')]);

            foreach ($order->legs as $leg) {
                $legStage = $this->inviteService->normalizeStageIdentifier((string) $leg->description);
                if ($legStage !== $stage) {
                    continue;
                }

                foreach ($leg->routePoints->sortByDesc('sequence') as $point) {
                    if ($point->type !== 'unloading' || $point->actual_date === null) {
                        continue;
                    }

                    return $point->actual_date->toDateString();
                }
            }
        }

        return null;
    }

    private function unloadingActualForCustomerOrder(Order $order): ?string
    {
        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $unloading = PerformerRouteActualDates::normalizeDate($slot['unloading_actual'] ?? null);
                    if ($unloading !== null) {
                        return $unloading;
                    }
                }

                continue;
            }

            $unloading = PerformerRouteActualDates::normalizeDate($performer['unloading_actual'] ?? null);
            if ($unloading !== null) {
                return $unloading;
            }
        }

        if ($order->relationLoaded('legs') || Schema::hasTable('order_legs')) {
            $order->loadMissing(['legs.routePoints' => fn ($q) => $q->orderByDesc('sequence')]);

            foreach ($order->legs as $leg) {
                foreach ($leg->routePoints->sortByDesc('sequence') as $point) {
                    if ($point->type !== 'unloading' || $point->actual_date === null) {
                        continue;
                    }

                    return $point->actual_date->toDateString();
                }
            }
        }

        return null;
    }
}
