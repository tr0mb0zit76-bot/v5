<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use App\Models\ImportCostReferenceSync;
use App\Models\ImportCostTnVedEntry;
use App\Support\ImportCostTnVedCatalog;
use Illuminate\Support\Facades\Schema;

final class EecTnVedSyncService
{
    public function __construct(
        private readonly EecODataClient $client,
    ) {}

    /**
     * @return array{status: string, items_updated: int, message: string, meta: array<string, mixed>}
     */
    public function sync(): array
    {
        if (! Schema::hasTable('import_cost_tn_ved_entries')) {
            return [
                'status' => 'failed',
                'items_updated' => 0,
                'message' => 'Таблица import_cost_tn_ved_entries отсутствует.',
                'meta' => [],
            ];
        }

        $seeded = $this->seedFromConfig();
        $registryTitles = $this->client->registryTitlesForKeywords();
        $prefixes = config('import_cost_calculator.eec.code_prefixes', []);
        $pageSize = (int) config('import_cost_calculator.eec.page_size', 200);
        $updated = $seeded;
        $matchedRows = 0;

        foreach ($registryTitles as $title) {
            $skip = 0;

            do {
                $rows = $this->client->listItems($title, $pageSize, $skip);
                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    $code = $this->client->extractTnVedCode($row);
                    if ($code === null || ! $this->matchesPrefixes($code, $prefixes)) {
                        continue;
                    }

                    $matchedRows++;
                    $duty = $this->client->extractDutyPercent($row);
                    $label = $this->client->extractLabel($row);

                    $entry = ImportCostTnVedEntry::query()->where('code', $code)->first();
                    if ($entry === null) {
                        continue;
                    }

                    $changes = false;

                    if ($duty !== null && abs((float) $entry->duty_percent - $duty) > 0.0001) {
                        $entry->duty_percent = $duty;
                        $entry->duty_source = 'eec';
                        $changes = true;
                    }

                    if ($label !== null && $label !== $entry->label) {
                        $entry->label = $label;
                        $changes = true;
                    }

                    if ($changes || $entry->eec_synced_at === null) {
                        $entry->eec_payload = $row;
                        $entry->eec_synced_at = now();
                        $entry->save();
                        $updated++;
                    }
                }

                $skip += $pageSize;
            } while (count($rows) === $pageSize);
        }

        $status = $registryTitles === [] ? 'partial' : ($matchedRows > 0 ? 'success' : 'partial');
        $message = $registryTitles === []
            ? 'ЕЭК OData: реестры не найдены, используются локальные ставки из config/БД.'
            : "ЕЭК OData: обновлено {$updated} код(ов), просмотрено {$matchedRows} строк.";

        $log = [
            'status' => $status,
            'items_updated' => $updated,
            'message' => $message,
            'meta' => [
                'registry_titles' => $registryTitles,
                'seeded_from_config' => $seeded,
                'matched_rows' => $matchedRows,
            ],
        ];

        $this->logSync($log);

        return $log;
    }

    public function seedFromConfig(): int
    {
        $count = 0;
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

        foreach (config('import_cost_calculator.tn_ved_codes', []) as $row) {
            if (! is_array($row) || blank($row['code'] ?? null)) {
                continue;
            }

            $code = ImportCostTnVedCatalog::normalizeCode((string) $row['code']);
            $categoryKey = (string) ($row['pp1291_category_key'] ?? $row['utilization_profile'] ?? '');

            ImportCostTnVedEntry::query()->updateOrCreate(
                ['code' => $code],
                [
                    'code_display' => (string) ($row['code_display'] ?? ImportCostTnVedCatalog::formatDisplayCode($code)),
                    'label' => (string) ($row['label'] ?? $code),
                    'duty_percent' => (float) ($row['duty_percent'] ?? 0),
                    'vat_percent' => isset($row['vat_percent']) && $row['vat_percent'] !== null
                        ? (float) $row['vat_percent']
                        : $defaultVat,
                    'pp1291_category_key' => $categoryKey !== '' ? $categoryKey : null,
                    'requires_utilization_fee' => (bool) ($row['requires_utilization_fee'] ?? false),
                    'duty_source' => 'config',
                    'is_active' => true,
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function matchesPrefixes(string $code, array $prefixes): bool
    {
        if ($prefixes === []) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($code, (string) $prefix)) {
                return true;
            }
        }

        return false;
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
            'source' => 'eec',
            'status' => $log['status'],
            'items_updated' => $log['items_updated'],
            'message' => $log['message'],
            'meta' => $log['meta'],
            'synced_at' => now(),
        ]);
    }
}
