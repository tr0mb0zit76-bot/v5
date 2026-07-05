<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class CounterpartyOrderAccess
{
    public function userCanViewOrder(User $user, Order $order): bool
    {
        if (! $user->isExternal()) {
            return false;
        }

        $party = $user->externalParty();
        $contractorId = (int) $user->contractor_id;

        if ($contractorId <= 0 || $party === null) {
            return false;
        }

        return match ($party) {
            ExternalParty::Customer => (int) $order->customer_id === $contractorId,
            ExternalParty::Carrier => $this->orderHasCarrierContractor($order, $contractorId),
        };
    }

    /**
     * @return Builder<Order>
     */
    public function ordersQueryForUser(User $user): Builder
    {
        $query = Order::query();

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('orders', 'is_active')) {
            $query->where('is_active', true);
        }

        if (! $user->isExternal()) {
            return $query->whereRaw('1 = 0');
        }

        $contractorId = (int) $user->contractor_id;
        $party = $user->externalParty();

        if ($contractorId <= 0 || $party === null) {
            return $query->whereRaw('1 = 0');
        }

        return match ($party) {
            ExternalParty::Customer => $query->where('customer_id', $contractorId),
            ExternalParty::Carrier => $query->where(function (Builder $builder) use ($contractorId): void {
                $builder
                    ->where(function (Builder $performersQuery) use ($contractorId): void {
                        if (! Schema::hasColumn('orders', 'performers')) {
                            $performersQuery->whereRaw('1 = 0');

                            return;
                        }

                        $performersQuery
                            ->whereNotNull('performers')
                            ->where('performers', 'like', '%"contractor_id":'.$contractorId.'%');
                    })
                    ->when(
                        Schema::hasTable('order_portal_invites'),
                        fn (Builder $outer) => $outer->orWhereExists(function ($sub) use ($contractorId): void {
                            $sub->selectRaw('1')
                                ->from('order_portal_invites')
                                ->whereColumn('order_portal_invites.order_id', 'orders.id')
                                ->where('order_portal_invites.contractor_id', $contractorId)
                                ->whereNull('order_portal_invites.revoked_at');
                        }),
                    );
            }),
        };
    }

    private function orderHasCarrierContractor(Order $order, int $contractorId): bool
    {
        if ($this->performersContainContractor($order, $contractorId)) {
            return true;
        }

        if (! Schema::hasTable('order_portal_invites')) {
            return false;
        }

        return OrderPortalInvite::query()
            ->where('order_id', $order->id)
            ->where('contractor_id', $contractorId)
            ->whereNull('revoked_at')
            ->exists();
    }

    private function performersContainContractor(Order $order, int $contractorId): bool
    {
        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if ((int) ($performer['contractor_id'] ?? 0) === $contractorId) {
                return true;
            }

            if (($performer['carrier_mode'] ?? '') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (is_array($slot) && (int) ($slot['contractor_id'] ?? 0) === $contractorId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
