<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lead;
use App\Models\LoadingPlannerProject;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class LoadingPlannerAccess
{
    public static function canAccessLead(?User $user, Lead $lead): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        return $scope === 'all' || (int) $lead->responsible_id === (int) $user->id;
    }

    public static function canAccessOrder(?User $user, Order $order): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

        return $scope === 'all' || (int) $order->manager_id === (int) $user->id;
    }

    public static function canViewProject(?User $user, LoadingPlannerProject $project): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) $project->user_id === (int) $user->id) {
            return true;
        }

        if ($project->order_id !== null) {
            $order = $project->relationLoaded('order')
                ? $project->order
                : Order::query()->find($project->order_id);

            return $order instanceof Order && self::canAccessOrder($user, $order);
        }

        if ($project->lead_id !== null) {
            $lead = $project->relationLoaded('lead')
                ? $project->lead
                : Lead::query()->find($project->lead_id);

            return $lead instanceof Lead && self::canAccessLead($user, $lead);
        }

        return false;
    }

    public static function canMutateProject(?User $user, LoadingPlannerProject $project): bool
    {
        return self::canViewProject($user, $project);
    }

    /**
     * @param  Builder<LoadingPlannerProject>  $query
     * @return Builder<LoadingPlannerProject>
     */
    public static function applyVisibleProjectsScope(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('user_id', $user->id);

            if (Schema::hasColumn('loading_planner_projects', 'order_id')) {
                $builder->orWhere(function (Builder $linkedOrders) use ($user): void {
                    $linkedOrders->whereNotNull('order_id')
                        ->whereExists(function ($sub) use ($user): void {
                            $sub->selectRaw('1')
                                ->from('orders')
                                ->whereColumn('orders.id', 'loading_planner_projects.order_id');

                            if (! $user->isAdmin()) {
                                if (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
                                    $sub->whereRaw('1 = 0');

                                    return;
                                }

                                $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');
                                if ($scope !== 'all') {
                                    $sub->where('orders.manager_id', $user->id);
                                }
                            }
                        });
                });
            }

            if (Schema::hasColumn('loading_planner_projects', 'lead_id')) {
                $builder->orWhere(function (Builder $linkedLeads) use ($user): void {
                    $linkedLeads->whereNotNull('lead_id')
                        ->whereExists(function ($sub) use ($user): void {
                            $sub->selectRaw('1')
                                ->from('leads')
                                ->whereColumn('leads.id', 'loading_planner_projects.lead_id');

                            if (Schema::hasColumn('leads', 'deleted_at')) {
                                $sub->whereNull('leads.deleted_at');
                            }

                            if (! $user->isAdmin()) {
                                if (! RoleAccess::canAccessVisibilityArea($user, 'leads')) {
                                    $sub->whereRaw('1 = 0');

                                    return;
                                }

                                $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');
                                if ($scope !== 'all') {
                                    $sub->where('leads.responsible_id', $user->id);
                                }
                            }
                        });
                });
            }
        });
    }
}
