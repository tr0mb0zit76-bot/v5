<?php

declare(strict_types=1);

namespace App\Support;

final class ImportCostTnVedCategoryResolver
{
    /**
     * @return array{category: string|null, requires_utilization_fee: bool}
     */
    public static function resolveForCode(string $code): array
    {
        $normalized = ImportCostTnVedCatalog::normalizeCode($code);
        $prefixes = config('import_cost_calculator.pp1291_prefix_map', []);
        $bestLength = 0;
        $category = null;

        foreach ($prefixes as $row) {
            if (! is_array($row)) {
                continue;
            }

            $prefix = (string) ($row['prefix'] ?? '');
            $key = (string) ($row['category'] ?? '');

            if ($prefix === '' || $key === '') {
                continue;
            }

            if (str_starts_with($normalized, $prefix) && strlen($prefix) > $bestLength) {
                $bestLength = strlen($prefix);
                $category = $key;
            }
        }

        $equipmentPrefixes = config('import_cost_calculator.eec.code_prefixes', []);

        return [
            'category' => $category,
            'requires_utilization_fee' => $category !== null
                || self::matchesAnyPrefix($normalized, $equipmentPrefixes),
        ];
    }

    /**
     * @param  list<string>  $prefixes
     */
    public static function matchesAnyPrefix(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($code, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }
}
