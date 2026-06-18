<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use App\Models\ImportCostReferenceSync;
use App\Models\ImportCostTnVedEntry;
use App\Support\ImportCostTnVedCatalog;
use App\Support\ImportCostTnVedCategoryResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

final class KodTnVedReferenceSyncService
{
    public function __construct(
        private readonly KodTnVedPageParser $parser,
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

        $limit = $limit ?? (int) config('import_cost_calculator.kodtnved.batch_limit', 200);
        $delayMs = max(0, (int) config('import_cost_calculator.kodtnved.delay_ms', 1000));
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $query = ImportCostTnVedEntry::query()
            ->where('is_active', true)
            ->where(function ($builder): void {
                $builder
                    ->where('duty_source', 'config')
                    ->orWhereNull('kodtnved_synced_at')
                    ->orWhere(function ($nested): void {
                        $nested->where('duty_percent', 0)
                            ->whereIn('duty_source', ['config', 'eec']);
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
        $message = "kodtnved.ru: обновлено {$updated}, пропущено {$skipped}, ошибок {$failed}.";

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

        $delayMs = max(0, (int) config('import_cost_calculator.kodtnved.delay_ms', 1000));
        $this->syncEntry($entry, $delayMs);

        return $entry->fresh();
    }

    private function shouldEnrich(ImportCostTnVedEntry $entry): bool
    {
        if ($entry->duty_source === 'kodtnved' && $entry->duty_percent > 0 && $entry->kodtnved_synced_at !== null) {
            return false;
        }

        if ($entry->duty_source === 'eec' && $entry->duty_percent > 0) {
            return false;
        }

        return true;
    }

    /**
     * @return bool|null true = updated, false = failed, null = skipped
     */
    private function syncEntry(ImportCostTnVedEntry $entry, int $delayMs): ?bool
    {
        $html = $this->fetchPageHtml($entry->code);

        if ($html === null) {
            return false;
        }

        $parsed = $this->parser->parse($html);

        if ($parsed === null) {
            return false;
        }

        $changes = false;
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

        if ($parsed['label'] !== null && $parsed['label'] !== $entry->label) {
            $entry->label = $parsed['label'];
            $changes = true;
        }

        if ($parsed['duty_percent'] !== null && $this->shouldApplyKodtnvedDuty($entry, $parsed['duty_percent'])) {
            $entry->duty_percent = $parsed['duty_percent'];
            $entry->duty_source = 'kodtnved';
            $changes = true;
        }

        if ($parsed['vat_percent'] !== null) {
            $entry->vat_percent = $parsed['vat_percent'];
            $changes = true;
        } elseif ($entry->vat_percent <= 0) {
            $entry->vat_percent = $defaultVat;
            $changes = true;
        }

        $entry->kodtnved_payload = $parsed;
        $entry->kodtnved_synced_at = now();

        if ($changes || $entry->wasRecentlyCreated) {
            $entry->save();
        } else {
            $entry->save();
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $changes || $parsed['duty_percent'] !== null ? true : null;
    }

    private function shouldApplyKodtnvedDuty(ImportCostTnVedEntry $entry, float $dutyPercent): bool
    {
        if ($entry->duty_source === 'eec' && $entry->duty_percent > 0) {
            return false;
        }

        if ($entry->duty_source === 'kodtnved' && abs($entry->duty_percent - $dutyPercent) < 0.0001) {
            return false;
        }

        return true;
    }

    private function fetchPageHtml(string $code): ?string
    {
        $baseUrl = rtrim((string) config('import_cost_calculator.kodtnved.base_url', 'https://kodtnved.ru'), '/');
        $timeout = (int) config('import_cost_calculator.kodtnved.timeout_seconds', 30);
        $url = $baseUrl.'/ts/'.$code.'.html';

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'AvtoalyansCrmImportCost/1.0 (+internal sync)',
                    'Accept' => 'text/html',
                ])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
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
            'source' => 'kodtnved',
            'status' => $log['status'],
            'items_updated' => $log['items_updated'],
            'message' => $log['message'],
            'meta' => $log['meta'],
            'synced_at' => now(),
        ]);
    }
}
