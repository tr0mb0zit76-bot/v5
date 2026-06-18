<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use App\Models\ImportCostReferenceSync;
use App\Models\ImportCostTnVedEntry;
use App\Support\ImportCostTnVedCatalog;
use App\Support\ImportCostTnVedCategoryResolver;
use Illuminate\Support\Facades\Schema;

final class AltaReferenceSyncService
{
    public function __construct(
        private readonly AltaSpravkaApiClient $client,
        private readonly AltaSpravkaResponseParser $parser,
    ) {}

    /**
     * @return array{status: string, items_updated: int, message: string, meta: array<string, mixed>}
     */
    public function sync(?int $limit = null): array
    {
        if (! Schema::hasTable('import_cost_tn_ved_entries')) {
            return [
                'status' => 'failed',
                'items_updated' => 0,
                'message' => 'Таблица import_cost_tn_ved_entries отсутствует.',
                'meta' => [],
            ];
        }

        if (! $this->client->isConfigured()) {
            $log = [
                'status' => 'partial',
                'items_updated' => 0,
                'message' => 'Alta API: учётные данные не настроены (IMPORT_COST_ALTA_LOGIN / IMPORT_COST_ALTA_PASSWORD).',
                'meta' => ['skipped' => true, 'reason' => 'credentials_missing'],
            ];

            $this->logSync($log);

            return $log;
        }

        $limit = $limit ?? (int) config('import_cost_calculator.alta.batch_limit', 200);
        $delayMs = max(0, (int) config('import_cost_calculator.alta.delay_ms', 500));
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $query = ImportCostTnVedEntry::query()
            ->where('is_active', true)
            ->where(function ($builder): void {
                $builder
                    ->where('duty_source', 'config')
                    ->orWhereNull('alta_synced_at')
                    ->orWhere(function ($nested): void {
                        $nested->where('duty_percent', 0)
                            ->whereIn('duty_source', ['config', 'eec', 'kodtnved']);
                    });
            })
            ->orderBy('code');

        if ($limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->get() as $entry) {
            if (! $this->shouldEnrich($entry)) {
                $skipped++;

                continue;
            }

            $result = $this->syncEntry($entry, $delayMs);

            if ($result === true) {
                $updated++;
            } elseif ($result === false) {
                $failed++;
            } else {
                $skipped++;
            }
        }

        $status = $failed > 0 && $updated === 0 ? 'partial' : ($updated > 0 ? 'success' : 'partial');
        $message = "Alta API: обновлено {$updated}, пропущено {$skipped}, ошибок {$failed}.";

        $log = [
            'status' => $status,
            'items_updated' => $updated,
            'message' => $message,
            'meta' => [
                'skipped' => $skipped,
                'failed' => $failed,
                'limit' => $limit,
            ],
        ];

        $this->logSync($log);

        return $log;
    }

    public function syncCode(string $code): ?ImportCostTnVedEntry
    {
        if (! $this->client->isConfigured()) {
            return null;
        }

        $normalized = ImportCostTnVedCatalog::normalizeCode($code);
        $prefixes = config('import_cost_calculator.eec.code_prefixes', []);

        if (! ImportCostTnVedCategoryResolver::matchesAnyPrefix($normalized, $prefixes)) {
            return null;
        }

        $resolved = ImportCostTnVedCategoryResolver::resolveForCode($normalized);
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

        $entry = ImportCostTnVedEntry::query()->firstOrCreate(
            ['code' => $normalized],
            [
                'code_display' => ImportCostTnVedCatalog::formatDisplayCode($normalized),
                'label' => $normalized,
                'duty_percent' => 0,
                'vat_percent' => $defaultVat,
                'pp1291_category_key' => $resolved['category'],
                'requires_utilization_fee' => $resolved['requires_utilization_fee'],
                'duty_source' => 'config',
                'is_active' => true,
            ],
        );

        $delayMs = max(0, (int) config('import_cost_calculator.alta.delay_ms', 500));
        $this->syncEntry($entry, $delayMs);

        return $entry->fresh();
    }

    private function shouldEnrich(ImportCostTnVedEntry $entry): bool
    {
        if ($entry->duty_source === 'alta' && $entry->duty_percent > 0 && $entry->alta_synced_at !== null) {
            return false;
        }

        return true;
    }

    /**
     * @return bool|null true = updated, false = failed, null = skipped
     */
    private function syncEntry(ImportCostTnVedEntry $entry, int $delayMs): ?bool
    {
        $response = $this->client->fetchGoodInfo($entry->code);

        if ($response === null) {
            return false;
        }

        if ($response['status'] !== 200) {
            return false;
        }

        $parsed = $this->parser->parse($response['body']);

        if ($parsed === null) {
            return false;
        }

        if ($parsed['error_code'] !== null) {
            return false;
        }

        if ($parsed['duty_percent'] === null && $parsed['vat_percent'] === null && $parsed['label'] === null) {
            return false;
        }

        $changes = false;
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

        if ($parsed['label'] !== null && $parsed['label'] !== $entry->label) {
            $entry->label = $parsed['label'];
            $changes = true;
        }

        if ($parsed['duty_percent'] !== null && $this->shouldApplyAltaDuty($entry, $parsed['duty_percent'])) {
            $entry->duty_percent = $parsed['duty_percent'];
            $entry->duty_source = 'alta';
            $changes = true;
        }

        if ($parsed['vat_percent'] !== null) {
            $entry->vat_percent = $parsed['vat_percent'];
            $changes = true;
        } elseif ($entry->vat_percent <= 0) {
            $entry->vat_percent = $defaultVat;
            $changes = true;
        }

        $entry->alta_payload = $parsed;
        $entry->alta_synced_at = now();
        $entry->save();

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $changes || $parsed['duty_percent'] !== null ? true : null;
    }

    private function shouldApplyAltaDuty(ImportCostTnVedEntry $entry, float $dutyPercent): bool
    {
        if ($entry->duty_source === 'alta' && abs($entry->duty_percent - $dutyPercent) < 0.0001) {
            return false;
        }

        return true;
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
            'source' => 'alta',
            'status' => $log['status'],
            'items_updated' => $log['items_updated'],
            'message' => $log['message'],
            'meta' => $log['meta'],
            'synced_at' => now(),
        ]);
    }
}
