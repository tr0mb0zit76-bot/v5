<?php

namespace App\Support;

final class TableColumnsPreset
{
    /**
     * @param  list<array{colId: string, hide: bool, width: int, order: int}>  $preset
     * @param  list<array{field: string, width?: int, minWidth?: int}>  $options
     * @return list<array{colId: string, hide: bool, width: int, order: int}>
     */
    public static function mergeWithCatalog(array $preset, array $options): array
    {
        $byColId = [];

        foreach ($preset as $column) {
            if (! is_array($column) || ! isset($column['colId'])) {
                continue;
            }

            $byColId[(string) $column['colId']] = $column;
        }

        $merged = array_values(array_filter($preset, fn ($column): bool => is_array($column) && isset($column['colId'])));
        $nextOrder = 0;

        foreach ($merged as $column) {
            $nextOrder = max($nextOrder, (int) ($column['order'] ?? 0) + 1);
        }

        foreach ($options as $option) {
            $field = $option['field'] ?? null;

            if (! is_string($field) || $field === '' || isset($byColId[$field])) {
                continue;
            }

            $merged[] = [
                'colId' => $field,
                'hide' => true,
                'width' => (int) ($option['width'] ?? 120),
                'order' => $nextOrder,
            ];
            $nextOrder++;
        }

        usort($merged, fn (array $left, array $right): int => ($left['order'] ?? 0) <=> ($right['order'] ?? 0));

        return $merged;
    }
}
