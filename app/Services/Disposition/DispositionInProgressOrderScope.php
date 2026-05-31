<?php

namespace App\Services\Disposition;

use App\Models\Order;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class DispositionInProgressOrderScope
{
    public const string IN_PROGRESS_STATUS = 'in_progress';

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function apply(Builder $query, User $user): Builder
    {
        $query->whereRaw('COALESCE(manual_status, status) = ?', [self::IN_PROGRESS_STATUS]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (! RoleAccess::isAdminUser($user)) {
            $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

            if ($scope !== 'all') {
                $query->where('manager_id', $user->id);
            }
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public function orderIdsForUser(User $user): array
    {
        $builder = Order::query();
        $this->apply($builder, $user);

        return $builder
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function orderIdsForManager(int $managerId): array
    {
        $builder = Order::query()
            ->where('manager_id', $managerId);

        $builder->whereRaw('COALESCE(manual_status, status) = ?', [self::IN_PROGRESS_STATUS]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        return $builder
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
