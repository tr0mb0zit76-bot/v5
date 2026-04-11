<?php

namespace App\Support;

class ContractorTableColumns
{
    /**
     * @return list<array{field: string, label: string, width: int, minWidth: int, type: string|null}>
     */
    public static function options(): array
    {
        return [
            ['field' => 'name', 'label' => 'Название', 'width' => 240, 'minWidth' => 190, 'type' => null],
            ['field' => 'status_text', 'label' => 'Статус', 'width' => 130, 'minWidth' => 110, 'type' => null],
            ['field' => 'activity_types_label', 'label' => 'Вид деятельности', 'width' => 220, 'minWidth' => 180, 'type' => null],
            ['field' => 'type_label', 'label' => 'Тип', 'width' => 160, 'minWidth' => 140, 'type' => null],
            ['field' => 'inn', 'label' => 'ИНН', 'width' => 140, 'minWidth' => 120, 'type' => null],
            ['field' => 'primary_contact', 'label' => 'Основной контакт', 'width' => 220, 'minWidth' => 180, 'type' => null],
            ['field' => 'phone', 'label' => 'Телефон', 'width' => 170, 'minWidth' => 140, 'type' => null],
            ['field' => 'email', 'label' => 'Email', 'width' => 200, 'minWidth' => 170, 'type' => null],
            ['field' => 'contacts_count', 'label' => 'Контакты', 'width' => 110, 'minWidth' => 95, 'type' => 'numeric'],
            ['field' => 'orders_count', 'label' => 'Заказы', 'width' => 110, 'minWidth' => 95, 'type' => 'numeric'],
            ['field' => 'current_debt', 'label' => 'Текущий долг', 'width' => 150, 'minWidth' => 130, 'type' => 'numeric'],
            ['field' => 'is_verified', 'label' => 'Проверен', 'width' => 110, 'minWidth' => 95, 'type' => 'boolean'],
            ['field' => 'is_own_company', 'label' => 'Своя компания', 'width' => 130, 'minWidth' => 120, 'type' => 'boolean'],
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
                'name',
                'status_text',
                'activity_types_label',
                'inn',
                'primary_contact',
            ],
            default => [
                'name',
                'status_text',
                'activity_types_label',
                'type_label',
                'inn',
                'primary_contact',
                'phone',
                'email',
                'contacts_count',
                'orders_count',
                'is_verified',
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
