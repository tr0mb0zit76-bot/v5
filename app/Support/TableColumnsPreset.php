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

    /**
     * Объединяет пресеты нескольких ролей по colId.
     * Колонка доступна, если хотя бы одна роль разрешила её (hide = false).
     *
     * @param  list<list<array{colId: string, hide: bool, width: int, order: int}>>  $presets
     * @return list<array{colId: string, hide: bool, width: int, order: int}>
     */
    public static function unionPresetsByColId(array $presets): array
    {
        $byColId = [];

        foreach ($presets as $preset) {
            if (! is_array($preset)) {
                continue;
            }

            foreach ($preset as $column) {
                if (! is_array($column) || ! isset($column['colId'])) {
                    continue;
                }

                $colId = (string) $column['colId'];

                if (! isset($byColId[$colId])) {
                    $byColId[$colId] = $column;

                    continue;
                }

                $existing = $byColId[$colId];
                $byColId[$colId] = [
                    ...$existing,
                    ...$column,
                    'hide' => (bool) (($existing['hide'] ?? true) && ($column['hide'] ?? true)),
                    'width' => max((int) ($existing['width'] ?? 120), (int) ($column['width'] ?? 120)),
                    'order' => min((int) ($existing['order'] ?? PHP_INT_MAX), (int) ($column['order'] ?? PHP_INT_MAX)),
                ];
            }
        }

        $merged = array_values($byColId);
        usort($merged, fn (array $left, array $right): int => ($left['order'] ?? 0) <=> ($right['order'] ?? 0));

        return $merged;
    }
}
