<?php

namespace App\Support;

use App\Models\ImportCostReferenceSync;
use Illuminate\Support\Facades\Schema;

final class ImportCostReferenceMeta
{
    /**
     * @return array{
     *     eec: array{synced_at: string|null, status: string|null, message: string|null},
     *     alta: array{synced_at: string|null, status: string|null, message: string|null},
     *     kodtnved: array{synced_at: string|null, status: string|null, message: string|null},
     *     pp1291: array{synced_at: string|null, status: string|null, message: string|null, effective_from: string|null}
     * }
     */
    public static function forUi(): array
    {
        if (! Schema::hasTable('import_cost_reference_syncs')) {
            return [
                'eec' => ['synced_at' => null, 'status' => null, 'message' => null],
                'alta' => ['synced_at' => null, 'status' => null, 'message' => null],
                'kodtnved' => ['synced_at' => null, 'status' => null, 'message' => null],
                'pp1291' => [
                    'synced_at' => null,
                    'status' => null,
                    'message' => null,
                    'effective_from' => config('import_cost_pp1291.decree_effective_from'),
                ],
            ];
        }

        $eec = ImportCostReferenceSync::latestForSource('eec');
        $alta = ImportCostReferenceSync::latestForSource('alta');
        $kodtnved = ImportCostReferenceSync::latestForSource('kodtnved');
        $pp = ImportCostReferenceSync::latestForSource('pp1291');

        return [
            'eec' => [
                'synced_at' => $eec?->synced_at?->toIso8601String(),
                'status' => $eec?->status,
                'message' => $eec?->message,
            ],
            'alta' => [
                'synced_at' => $alta?->synced_at?->toIso8601String(),
                'status' => $alta?->status,
                'message' => $alta?->message,
            ],
            'kodtnved' => [
                'synced_at' => $kodtnved?->synced_at?->toIso8601String(),
                'status' => $kodtnved?->status,
                'message' => $kodtnved?->message,
            ],
            'pp1291' => [
                'synced_at' => $pp?->synced_at?->toIso8601String(),
                'status' => $pp?->status,
                'message' => $pp?->message,
                'effective_from' => (string) (config('import_cost_pp1291.decree_effective_from')
                    ?? $pp?->meta['effective_from'] ?? null),
            ],
        ];
    }
}
