<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class EecODataClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function metadataTitles(): array
    {
        $list = (string) config('import_cost_calculator.eec.metadata_list', 'Список метаданных');
        $response = $this->client()->get($this->listItemsUrl($list), [
            '$select' => 'MetadataList_title_name,MetadataList_title,MetadataList_subject_scope',
            '$top' => 5000,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $this->extractRows($response->json());
    }

    /**
     * @return list<string>
     */
    public function registryTitlesForKeywords(): array
    {
        $keywords = config('import_cost_calculator.eec.registry_title_keywords', []);
        $titles = [];

        foreach ($this->metadataTitles() as $row) {
            $name = mb_strtolower((string) ($row['MetadataList_title_name'] ?? $row['MetadataList_title'] ?? ''));
            foreach ($keywords as $keyword) {
                if ($name !== '' && str_contains($name, mb_strtolower((string) $keyword))) {
                    $title = (string) ($row['MetadataList_title_name'] ?? $row['MetadataList_title']);
                    if ($title !== '') {
                        $titles[] = $title;
                    }
                    break;
                }
            }
        }

        return array_values(array_unique($titles));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listItems(string $listTitle, int $top = 200, int $skip = 0): array
    {
        $response = $this->client()->get($this->listItemsUrl($listTitle), [
            '$top' => $top,
            '$skip' => $skip,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $this->extractRows($response->json());
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function extractTnVedCode(array $row): ?string
    {
        foreach (['Code', 'code', 'KOD', 'kod', 'TnvedCode', 'TNVED', 'tnved', 'Title', 'title'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', (string) $row[$key]) ?? '';
            if (strlen($digits) >= 4) {
                return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_RIGHT);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function extractDutyPercent(array $row): ?float
    {
        $candidates = [];

        foreach ($row as $key => $value) {
            $keyLower = mb_strtolower((string) $key);
            if (! is_scalar($value)) {
                continue;
            }

            if (
                str_contains($keyLower, 'duty')
                || str_contains($keyLower, 'poshl')
                || str_contains($keyLower, 'imp')
                || str_contains($keyLower, 'ставк')
                || str_contains($keyLower, 'rate')
                || str_contains($keyLower, 'ett')
            ) {
                $numeric = $this->toPercent((string) $value);
                if ($numeric !== null) {
                    $candidates[] = $numeric;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        return max(0.0, min(100.0, $candidates[0]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function extractVatPercent(array $row): ?float
    {
        $candidates = [];

        foreach ($row as $key => $value) {
            $keyLower = mb_strtolower((string) $key);
            if (! is_scalar($value)) {
                continue;
            }

            if (
                str_contains($keyLower, 'vat')
                || str_contains($keyLower, 'nds')
                || str_contains($keyLower, 'ндс')
            ) {
                $numeric = $this->toPercent((string) $value);
                if ($numeric !== null) {
                    $candidates[] = $numeric;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        return max(0.0, min(100.0, $candidates[0]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function extractLabel(array $row): ?string
    {
        foreach (['Name', 'name', 'NAIM', 'naim', 'Title', 'title', 'Description'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '' && ! preg_match('/^\d+$/', $value)) {
                return $value;
            }
        }

        return null;
    }

    private function client(): PendingRequest
    {
        $timeout = (int) config('import_cost_calculator.eec.timeout_seconds', 45);

        return Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/json;odata=verbose',
            ]);
    }

    private function listItemsUrl(string $listTitle): string
    {
        $base = rtrim((string) config('import_cost_calculator.eec.base_url'), '/');
        $encoded = rawurlencode($listTitle);

        return "{$base}/web/lists/getByTitle('{$encoded}')/Items";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRows(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $results = $payload['d']['results'] ?? $payload['value'] ?? $payload['d'] ?? null;

        if (is_array($results) && array_is_list($results)) {
            return array_values(array_filter($results, 'is_array'));
        }

        if (is_array($results) && $results !== []) {
            return [$results];
        }

        return [];
    }

    private function toPercent(string $raw): ?float
    {
        $raw = trim(Str::replace(',', '.', $raw));
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        if ($value > 0 && $value <= 1) {
            $value *= 100;
        }

        return round($value, 4);
    }
}
