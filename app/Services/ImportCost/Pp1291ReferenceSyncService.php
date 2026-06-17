<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use App\Models\ImportCostPp1291Category;
use App\Models\ImportCostReferenceSync;
use Illuminate\Support\Facades\Schema;

final class Pp1291ReferenceSyncService
{
    /**
     * @return array{status: string, items_updated: int, message: string, meta: array<string, mixed>}
     */
    public function sync(): array
    {
        if (! Schema::hasTable('import_cost_pp1291_categories')) {
            return [
                'status' => 'failed',
                'items_updated' => 0,
                'message' => 'Таблица import_cost_pp1291_categories отсутствует.',
                'meta' => [],
            ];
        }

        $categories = config('import_cost_pp1291.categories', []);
        $updated = 0;
        $effectiveFrom = config('import_cost_pp1291.decree_effective_from');
        $decree = (string) config('import_cost_pp1291.decree_reference', 'ПП РФ № 1291');

        foreach ($categories as $key => $category) {
            if (! is_array($category)) {
                continue;
            }

            ImportCostPp1291Category::query()->updateOrCreate(
                ['key' => (string) $key],
                [
                    'name' => (string) ($category['name'] ?? $key),
                    'base_fee_rub' => (int) ($category['base_fee_rub'] ?? 150_000),
                    'age_coefficients' => $category['age_coefficients'] ?? [],
                    'decree_reference' => $decree,
                    'effective_from' => $effectiveFrom,
                    'synced_at' => now(),
                ],
            );

            $updated++;
        }

        $log = [
            'status' => 'success',
            'items_updated' => $updated,
            'message' => "ПП № 1291: загружено {$updated} категорий утильсбора.",
            'meta' => [
                'decree_reference' => $decree,
                'effective_from' => $effectiveFrom,
            ],
        ];

        $this->logSync($log);

        return $log;
    }

    /**
     * @param  array{status: string, items_updated: int, message: string, meta: array<string, mixed>}  $log
     */
    private function logSync(array $log): void
    {
        if (! Schema::hasTable('import_cost_reference_syncs')) {
            return;
        }

        ImportCostReferenceSync::query()->create([
            'source' => 'pp1291',
            'status' => $log['status'],
            'items_updated' => $log['items_updated'],
            'message' => $log['message'],
            'meta' => $log['meta'],
            'synced_at' => now(),
        ]);
    }
}
