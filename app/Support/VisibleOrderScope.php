<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

final class VisibleOrderScope
{
    public static function apply(Builder $query): Builder
    {
        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }
}
