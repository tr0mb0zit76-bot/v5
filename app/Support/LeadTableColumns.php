<?php

namespace App\Support;

class LeadTableColumns
{
    /**
     * @return list<array{field: string, label: string, width: int, minWidth: int, type: string|null}>
     */
    public static function options(): array
    {
        return [
            ['field' => 'number', 'label' => '№ лида', 'width' => 120, 'minWidth' => 110, 'type' => null],
            ['field' => 'status', 'label' => 'Статус', 'width' => 150, 'minWidth' => 130, 'type' => null],
            ['field' => 'title', 'label' => 'Тема', 'width' => 220, 'minWidth' => 180, 'type' => null],
            ['field' => 'source', 'label' => 'Источник', 'width' => 150, 'minWidth' => 130, 'type' => null],
            ['field' => 'counterparty_name', 'label' => 'Контрагент', 'width' => 200, 'minWidth' => 160, 'type' => null],
            ['field' => 'responsible_name', 'label' => 'Ответственный', 'width' => 180, 'minWidth' => 150, 'type' => null],
            ['field' => 'planned_shipping_date', 'label' => 'План отгрузки', 'width' => 140, 'minWidth' => 120, 'type' => 'date'],
            ['field' => 'target_price', 'label' => 'Цена', 'width' => 130, 'minWidth' => 120, 'type' => 'numeric'],
            ['field' => 'target_currency', 'label' => 'Валюта', 'width' => 100, 'minWidth' => 90, 'type' => null],
            ['field' => 'has_offer', 'label' => 'Есть КП', 'width' => 110, 'minWidth' => 100, 'type' => 'boolean'],
            ['field' => 'created_at', 'label' => 'Создан', 'width' => 160, 'minWidth' => 140, 'type' => 'datetime'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return array_column(static::options(), 'field');
    }

    /**
     * @return list<string>
     */
    public static function defaultVisibleFields(string $roleName): array
    {
        return match ($roleName) {
            'manager' => [
                'number',
                'status',
                'title',
                'counterparty_name',
                'planned_shipping_date',
                'target_price',
                'has_offer',
            ],
            default => [
                'number',
                'status',
                'title',
                'source',
                'counterparty_name',
                'responsible_name',
                'planned_shipping_date',
                'target_price',
                'has_offer',
                'created_at',
            ],
        };
    }

    /**
     * @return list<array{colId: string, hide: bool, width: int, order: int}>
     */
    public static function defaultState(string $roleName): array
    {
        $visibleFields = static::defaultVisibleFields($roleName);

        return array_map(
            fn (array $column, int $index): array => [
                'colId' => $column['field'],
                'hide' => ! in_array($column['field'], $visibleFields, true),
                'width' => $column['width'],
                'order' => $index,
            ],
            static::options(),
            array_keys(static::options()),
        );
    }
}
