<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use App\Models\ImportCostReferenceSync;
use App\Models\ImportCostTnVedEntry;
use App\Support\ImportCostTnVedCatalog;
use App\Support\ImportCostTnVedCategoryResolver;
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

        $this->applyConfigCategoryHints();
        $registryTitles = $this->client->registryTitlesForKeywords();
        $prefixes = config('import_cost_calculator.eec.code_prefixes', []);
        $pageSize = (int) config('import_cost_calculator.eec.page_size', 200);
        $updated = 0;
        $created = 0;
        $matchedRows = 0;
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

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

                    if (strlen(preg_replace('/\D+/', '', $code) ?? '') < 4) {
                        continue;
                    }

                    $matchedRows++;
                    $duty = $this->client->extractDutyPercent($row);
                    $vat = $this->client->extractVatPercent($row);
                    $label = $this->client->extractLabel($row);
                    $resolved = ImportCostTnVedCategoryResolver::resolveForCode($code);

                    $entry = ImportCostTnVedEntry::query()->where('code', $code)->first();
                    $isNew = $entry === null;

                    if ($isNew) {
                        $entry = new ImportCostTnVedEntry([
                            'code' => $code,
                            'code_display' => ImportCostTnVedCatalog::formatDisplayCode($code),
                            'label' => $label ?? $code,
                            'duty_percent' => $duty ?? 0,
                            'vat_percent' => $vat ?? $defaultVat,
                            'pp1291_category_key' => $resolved['category'],
                            'requires_utilization_fee' => $resolved['requires_utilization_fee'],
                            'duty_source' => $duty !== null ? 'eec' : 'config',
                            'is_active' => true,
                        ]);
                        $created++;
                    }

                    $changes = $isNew;

                    if ($label !== null && $label !== $entry->label) {
                        $entry->label = $label;
                        $changes = true;
                    }

                    if ($duty !== null && abs((float) $entry->duty_percent - $duty) > 0.0001) {
                        $entry->duty_percent = $duty;
                        $entry->duty_source = 'eec';
                        $changes = true;
                    } elseif ($duty !== null && $entry->duty_source !== 'eec') {
                        $entry->duty_source = 'eec';
                        $changes = true;
                    }

                    if ($vat !== null && abs((float) $entry->vat_percent - $vat) > 0.0001) {
                        $entry->vat_percent = $vat;
                        $changes = true;
                    }

                    if ($entry->pp1291_category_key === null && $resolved['category'] !== null) {
                        $entry->pp1291_category_key = $resolved['category'];
                        $changes = true;
                    }

                    if ($resolved['requires_utilization_fee'] && ! $entry->requires_utilization_fee) {
                        $entry->requires_utilization_fee = true;
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
            ? 'ЕЭК OData: реестры не найдены, используются локальные ставки из БД.'
            : "ЕЭК OData: обновлено {$updated} код(ов) (новых {$created}), просмотрено {$matchedRows} строк.";

        $log = [
            'status' => $status,
            'items_updated' => $updated,
            'message' => $message,
            'meta' => [
                'registry_titles' => $registryTitles,
                'created' => $created,
                'matched_rows' => $matchedRows,
            ],
        ];

        $this->logSync($log);

        return $log;
    }

    public function seedFromConfig(): int
    {
        return $this->applyConfigCategoryHints();
    }

    public function applyConfigCategoryHints(): int
    {
        $count = 0;
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

        foreach (config('import_cost_calculator.tn_ved_codes', []) as $row) {
            if (! is_array($row) || blank($row['code'] ?? null)) {
                continue;
            }

            $code = ImportCostTnVedCatalog::normalizeCode((string) $row['code']);
            $categoryKey = (string) ($row['pp1291_category_key'] ?? $row['utilization_profile'] ?? '');
            $resolved = ImportCostTnVedCategoryResolver::resolveForCode($code);

            $entry = ImportCostTnVedEntry::query()->firstOrNew(['code' => $code]);
            $isNew = ! $entry->exists;

            if ($isNew) {
                $entry->code_display = (string) ($row['code_display'] ?? ImportCostTnVedCatalog::formatDisplayCode($code));
                $entry->label = (string) ($row['label'] ?? $code);
                $entry->duty_percent = (float) ($row['duty_percent'] ?? 0);
                $entry->vat_percent = isset($row['vat_percent']) && $row['vat_percent'] !== null
                    ? (float) $row['vat_percent']
                    : $defaultVat;
                $entry->duty_source = 'config';
                $entry->is_active = true;
            }

            $category = $categoryKey !== '' ? $categoryKey : $resolved['category'];
            if ($category !== null && $category !== '') {
                $entry->pp1291_category_key = $category;
            }

            $entry->requires_utilization_fee = (bool) ($row['requires_utilization_fee'] ?? $resolved['requires_utilization_fee']);

            if ($isNew || $entry->isDirty(['pp1291_category_key', 'requires_utilization_fee', 'label', 'code_display'])) {
                $entry->save();
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function matchesPrefixes(string $code, array $prefixes): bool
    {
        return ImportCostTnVedCategoryResolver::matchesAnyPrefix($code, $prefixes);
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
