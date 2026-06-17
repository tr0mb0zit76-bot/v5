<?php

namespace App\Support;

use App\Models\ImportCostTnVedEntry;
use Illuminate\Support\Facades\Schema;

final class ImportCostTnVedCatalog
{
    /**
     * @return list<array{
     *     code: string,
     *     code_display: string,
     *     label: string,
     *     duty_percent: float,
     *     vat_percent: float,
     *     pp1291_category_key: string|null,
     *     utilization_profile: string|null,
     *     requires_utilization_fee: bool,
     *     duty_source: string|null,
     *     search_text: string
     * }>
     */
    public static function all(): array
    {
        if (Schema::hasTable('import_cost_tn_ved_entries')) {
            $fromDb = ImportCostTnVedEntry::query()
                ->where('is_active', true)
                ->orderBy('code_display')
                ->get()
                ->map(fn (ImportCostTnVedEntry $entry): array => self::mapEntry($entry))
                ->all();

            if ($fromDb !== []) {
                return $fromDb;
            }
        }

        return self::fromConfig();
    }

    /**
     * @return array{
     *     code: string,
     *     code_display: string,
     *     label: string,
     *     duty_percent: float,
     *     vat_percent: float,
     *     pp1291_category_key: string|null,
     *     utilization_profile: string|null,
     *     requires_utilization_fee: bool,
     *     duty_source: string|null,
     *     search_text: string
     * }|null
     */
    public static function find(string $code): ?array
    {
        $normalized = self::normalizeCode($code);

        if (Schema::hasTable('import_cost_tn_ved_entries')) {
            $entry = ImportCostTnVedEntry::query()
                ->where('code', $normalized)
                ->where('is_active', true)
                ->first();

            if ($entry !== null) {
                return self::mapEntry($entry);
            }
        }

        foreach (self::fromConfig() as $row) {
            if ($row['code'] === $normalized) {
                return $row;
            }
        }

        return null;
    }

    public static function normalizeCode(string $code): string
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';

        return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_RIGHT);
    }

    public static function formatDisplayCode(string $code): string
    {
        $digits = self::normalizeCode($code);

        return substr($digits, 0, 4).'.'.substr($digits, 4, 2);
    }

    /**
     * @return list<array{
     *     code: string,
     *     code_display: string,
     *     label: string,
     *     duty_percent: float,
     *     vat_percent: float,
     *     pp1291_category_key: string|null,
     *     utilization_profile: string|null,
     *     requires_utilization_fee: bool,
     *     duty_source: string|null,
     *     search_text: string
     * }>
     */
    private static function fromConfig(): array
    {
        $defaultVat = (float) config('import_cost_calculator.default_vat_percent', 22);

        return collect(config('import_cost_calculator.tn_ved_codes', []))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['code'] ?? null))
            ->map(function (array $row) use ($defaultVat): array {
                $code = self::normalizeCode((string) $row['code']);
                $display = (string) ($row['code_display'] ?? self::formatDisplayCode($code));
                $label = (string) ($row['label'] ?? '');
                $category = filled($row['pp1291_category_key'] ?? null)
                    ? (string) $row['pp1291_category_key']
                    : (filled($row['utilization_profile'] ?? null) ? (string) $row['utilization_profile'] : null);

                return [
                    'code' => $code,
                    'code_display' => $display,
                    'label' => $label,
                    'duty_percent' => (float) ($row['duty_percent'] ?? 0),
                    'vat_percent' => isset($row['vat_percent']) && $row['vat_percent'] !== null
                        ? (float) $row['vat_percent']
                        : $defaultVat,
                    'pp1291_category_key' => $category,
                    'utilization_profile' => $category,
                    'requires_utilization_fee' => (bool) ($row['requires_utilization_fee'] ?? false),
                    'duty_source' => 'config',
                    'search_text' => mb_strtolower($display.' '.$code.' '.$label),
                ];
            })
            ->sortBy('code_display')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     code: string,
     *     code_display: string,
     *     label: string,
     *     duty_percent: float,
     *     vat_percent: float,
     *     pp1291_category_key: string|null,
     *     utilization_profile: string|null,
     *     requires_utilization_fee: bool,
     *     duty_source: string|null,
     *     search_text: string
     * }
     */
    private static function mapEntry(ImportCostTnVedEntry $entry): array
    {
        $display = $entry->code_display ?: self::formatDisplayCode($entry->code);
        $label = $entry->label;

        return [
            'code' => $entry->code,
            'code_display' => $display,
            'label' => $label,
            'duty_percent' => (float) $entry->duty_percent,
            'vat_percent' => (float) $entry->vat_percent,
            'pp1291_category_key' => $entry->pp1291_category_key,
            'utilization_profile' => $entry->pp1291_category_key,
            'requires_utilization_fee' => (bool) $entry->requires_utilization_fee,
            'duty_source' => $entry->duty_source,
            'search_text' => mb_strtolower($display.' '.$entry->code.' '.$label),
        ];
    }
}
