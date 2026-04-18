<?php

namespace App\Support;

class PaymentScheduleTableColumns
{
    /**
     * @return list<array{field: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['field' => 'order_number', 'label' => 'Заказ'],
            ['field' => 'direction', 'label' => 'Направление'],
            ['field' => 'counterparty_name', 'label' => 'Контрагент'],
            ['field' => 'payment_type', 'label' => 'Тип'],
            ['field' => 'invoice_number', 'label' => 'Номер счёта'],
            ['field' => 'planned_date', 'label' => 'План'],
            ['field' => 'actual_date', 'label' => 'Факт'],
            ['field' => 'amount', 'label' => 'Сумма'],
            ['field' => 'status', 'label' => 'Статус'],
            ['field' => 'actions', 'label' => 'Действия'],
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
     * @return list<array{colId: string, hide: bool, width: int, order: int}>
     */
    public static function defaultState(string $roleName): array
    {
        $defaults = [
            'order_number' => ['width' => 160, 'hide' => false],
            'direction' => ['width' => 140, 'hide' => false],
            'counterparty_name' => ['width' => 200, 'hide' => false],
            'payment_type' => ['width' => 130, 'hide' => false],
            'invoice_number' => ['width' => 150, 'hide' => false],
            'planned_date' => ['width' => 130, 'hide' => false],
            'actual_date' => ['width' => 130, 'hide' => false],
            'amount' => ['width' => 130, 'hide' => false],
            'status' => ['width' => 130, 'hide' => false],
            'actions' => ['width' => 160, 'hide' => false],
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
