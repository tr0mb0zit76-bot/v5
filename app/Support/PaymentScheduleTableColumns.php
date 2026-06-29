<?php

namespace App\Support;

class PaymentScheduleTableColumns
{
    /**
     * @return list<array{field: string, label: string, width: int, minWidth: int}>
     */
    public static function options(): array
    {
        return [
            ['field' => 'id', 'label' => 'ID', 'width' => 56, 'minWidth' => 48],
            ['field' => 'order_number', 'label' => 'Заказ', 'width' => 160, 'minWidth' => 120],
            ['field' => 'direction', 'label' => 'Направление', 'width' => 140, 'minWidth' => 110],
            ['field' => 'counterparty_name', 'label' => 'Контрагент', 'width' => 200, 'minWidth' => 160],
            ['field' => 'payment_type', 'label' => 'Тип', 'width' => 130, 'minWidth' => 110],
            ['field' => 'invoice_number', 'label' => 'Номер счёта', 'width' => 150, 'minWidth' => 120],
            ['field' => 'payment_run_date', 'label' => 'План оплаты', 'width' => 140, 'minWidth' => 120],
            ['field' => 'planned_date', 'label' => 'План', 'width' => 130, 'minWidth' => 110],
            ['field' => 'actual_date', 'label' => 'Факт', 'width' => 130, 'minWidth' => 110],
            ['field' => 'amount', 'label' => 'Сумма', 'width' => 130, 'minWidth' => 110],
            ['field' => 'status', 'label' => 'Статус', 'width' => 130, 'minWidth' => 110],
            ['field' => 'actions', 'label' => 'Действия', 'width' => 88, 'minWidth' => 72],
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
     * @param  list<array{colId: string, hide: bool, width: int, order: int}>  $preset
     * @return list<array{colId: string, hide: bool, width: int, order: int}>
     */
    public static function mergePresetWithCatalog(array $preset): array
    {
        return TableColumnsPreset::mergeWithCatalog($preset, static::options());
    }

    /**
     * @return list<array{colId: string, hide: bool, width: int, order: int}>
     */
    public static function defaultState(string $roleName): array
    {
        $defaults = [
            'id' => ['width' => 56, 'hide' => false],
            'order_number' => ['width' => 160, 'hide' => false],
            'direction' => ['width' => 140, 'hide' => false],
            'counterparty_name' => ['width' => 200, 'hide' => false],
            'payment_type' => ['width' => 130, 'hide' => false],
            'invoice_number' => ['width' => 150, 'hide' => false],
            'payment_run_date' => ['width' => 140, 'hide' => false],
            'planned_date' => ['width' => 130, 'hide' => false],
            'actual_date' => ['width' => 130, 'hide' => false],
            'amount' => ['width' => 130, 'hide' => false],
            'status' => ['width' => 130, 'hide' => false],
            'actions' => ['width' => 88, 'hide' => false],
        ];

        if ($roleName === 'viewer') {
            $defaults['amount']['hide'] = true;
        }

        if (! in_array($roleName, ['admin', 'supervisor', 'accountant'], true)) {
            $defaults['actions']['hide'] = true;
        }

        return array_values(array_map(
            static fn (array $column, int $order): array => [
                'colId' => $column['field'],
                'hide' => (bool) ($defaults[$column['field']]['hide'] ?? false),
                'width' => (int) ($defaults[$column['field']]['width'] ?? 140),
                'order' => $order,
            ],
            static::options(),
            array_keys(static::options()),
        ));
    }
}
