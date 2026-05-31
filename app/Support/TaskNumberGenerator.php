<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TaskNumberGenerator
{
    public function next(): string
    {
        if (! Schema::hasTable('tasks')) {
            return 'TSK-'.now()->format('ymd').'-001';
        }

        $prefix = 'TSK-'.now()->format('ymd');
        $sequence = DB::table('tasks')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
