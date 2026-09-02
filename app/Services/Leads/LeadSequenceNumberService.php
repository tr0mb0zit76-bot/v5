<?php

declare(strict_types=1);

namespace App\Services\Leads;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Последовательные номера лидов/задач (единый алгоритм вместо DB:: в контроллерах).
 */
final class LeadSequenceNumberService
{
    public function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = DB::table('leads')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    public function nextTaskNumber(): string
    {
        $prefix = 'TSK-'.now()->format('ymd');

        if (! Schema::hasTable('tasks')) {
            return sprintf('%s-%03d', $prefix, 1);
        }

        $sequence = DB::table('tasks')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
