<?php

namespace App\Services\Disposition;

use App\Models\Order;
use App\Models\User;
use App\Support\DispositionInTransitResolver;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class DispositionInProgressOrderScope
{
    public const string IN_PROGRESS_STATUS = 'in_progress';

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function applyVisibility(Builder $query, User $user): Builder
    {
        return $this->applyVisibilityForArea($query, $user, 'orders');
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function applyVisibilityForArea(Builder $query, User $user, string $area): Builder
    {
        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (! RoleAccess::isAdminUser($user) && ! $user->isSupervisor()) {
            OrderViewAuthorization::applyOrdersVisibilityScope($query, $user, $area);
        }

        return $query;
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function apply(Builder $query, User $user): Builder
    {
        return $this->applyVisibility($query, $user);
    }

    /**
     * @return list<int>
     */
    public function orderIdsForUser(User $user): array
    {
        return $this->filterInTransit(
            $this->baseOrdersQuery()
                ->tap(fn (Builder $query) => $this->applyVisibility($query, $user))
                ->get(),
        );
    }

    /**
     * @return list<int>
     */
    public function orderIdsForManager(int $managerId): array
    {
        $builder = Order::query()->where('manager_id', $managerId);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        return $this->filterInTransit($builder->get());
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<int>
     */
    public function filterInTransit(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $eloquentOrders = $orders instanceof EloquentCollection
            ? $orders
            : EloquentCollection::make($orders->all());

        $eloquentOrders->loadMissing([
            'legs' => fn ($query) => $query->orderBy('sequence'),
            'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
        ]);

        return $eloquentOrders
            ->filter(fn (Order $order): bool => DispositionInTransitResolver::isInTransit($order))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Builder<Order>
     */
    private function baseOrdersQuery(): Builder
    {
        return Order::query()->select('orders.*');
    }
}
