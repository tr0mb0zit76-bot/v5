<?php

namespace App\Support;

final class SalesBookPropertyCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     multiple: bool,
     *     options: list<array{value: string, label: string}>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'audience_role',
                'label' => 'Для кого',
                'type' => 'select',
                'multiple' => false,
                'options' => [
                    ['value' => 'manager', 'label' => 'Менеджер'],
                    ['value' => 'supervisor', 'label' => 'Руководитель'],
                    ['value' => 'logist', 'label' => 'Логист'],
                    ['value' => 'newcomer', 'label' => 'Новичок'],
                ],
            ],
            [
                'key' => 'sales_stage',
                'label' => 'Этап продаж',
                'type' => 'select',
                'multiple' => false,
                'options' => [
                    ['value' => 'lead', 'label' => 'Лид'],
                    ['value' => 'qualification', 'label' => 'Квалификация'],
                    ['value' => 'offer', 'label' => 'КП'],
                    ['value' => 'negotiation', 'label' => 'Переговоры'],
                    ['value' => 'closing', 'label' => 'Закрытие'],
                    ['value' => 'retention', 'label' => 'Повторная продажа'],
                ],
            ],
            [
                'key' => 'product_area',
                'label' => 'Направление',
                'type' => 'select',
                'multiple' => false,
                'options' => [
                    ['value' => 'road', 'label' => 'Автоперевозки'],
                    ['value' => 'import', 'label' => 'Импорт'],
                    ['value' => 'documents', 'label' => 'Документы'],
                    ['value' => 'finance', 'label' => 'Финансы'],
                    ['value' => 'crm', 'label' => 'CRM'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function optionLabelsByProperty(): array
    {
        $labels = [];

        foreach (self::definitions() as $definition) {
            $labels[$definition['key']] = collect($definition['options'])
                ->mapWithKeys(fn (array $option): array => [$option['value'] => $option['label']])
                ->all();
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            fn (array $definition): string => $definition['key'],
            self::definitions(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(mixed $properties): array
    {
        if (! is_array($properties)) {
            return [];
        }

        $normalized = [];

        foreach (self::definitions() as $definition) {
            $key = $definition['key'];
            $allowed = array_column($definition['options'], 'value');
            $value = $properties[$key] ?? null;

            if ($definition['multiple']) {
                $values = is_array($value) ? $value : [$value];
                $filtered = collect($values)
                    ->map(fn (mixed $item): string => trim((string) $item))
                    ->filter(fn (string $item): bool => in_array($item, $allowed, true))
                    ->unique()
                    ->values()
                    ->all();

                if ($filtered !== []) {
                    $normalized[$key] = $filtered;
                }

                continue;
            }

            $single = trim((string) $value);
            if ($single !== '' && in_array($single, $allowed, true)) {
                $normalized[$key] = $single;
            }
        }

        return $normalized;
    }
}
