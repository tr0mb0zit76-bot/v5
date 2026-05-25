<?php

namespace App\Support;

/**
 * Готовые наборы кнопок нижней панели PWA для типовых ролей (применяются в админке и в профиле PWA).
 */
final class MobileNavPresets
{
    /**
     * @var array<string, array{label: string, description: string, keys: list<string>}>
     */
    private const PRESETS = [
        'manager' => [
            'label' => 'Менеджер',
            'description' => 'Заказы, задачи, лиды, тренажёр',
            'keys' => ['dashboard', 'orders', 'tasks', 'leads', 'trainer'],
        ],
        'leadership' => [
            'label' => 'Руководство',
            'description' => 'Задачи, канбан, отчёты, документы',
            'keys' => ['dashboard', 'tasks', 'kanban', 'reports', 'documents'],
        ],
        'management' => [
            'label' => 'Управление',
            'description' => 'Финансы, отчёты, документы, задачи',
            'keys' => ['dashboard', 'finance', 'reports', 'documents', 'tasks'],
        ],
    ];

    /**
     * @return list<array{id: string, label: string, description: string, keys: list<string>}>
     */
    public static function optionsForUi(): array
    {
        $out = [];
        foreach (self::PRESETS as $id => $preset) {
            $out[] = [
                'id' => $id,
                'label' => $preset['label'],
                'description' => $preset['description'],
                'keys' => $preset['keys'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $candidateKeys
     * @return list<string>
     */
    public static function resolveForCandidates(string $presetId, array $candidateKeys): array
    {
        $preset = self::PRESETS[$presetId] ?? null;
        if ($preset === null) {
            return [];
        }

        $allowed = array_flip($candidateKeys);
        $picked = [];

        foreach ($preset['keys'] as $key) {
            if (! isset($allowed[$key]) || in_array($key, $picked, true)) {
                continue;
            }

            $picked[] = $key;

            if (count($picked) >= MobileNavCatalog::maxSelectable()) {
                break;
            }
        }

        return $picked;
    }
}
