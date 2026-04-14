<?php

namespace App\Support;

class OrderTableColumns
{
    /**
     * @return list<array{field: string, label: string, width: int, minWidth: int, type: string|null}>
     */
    public static function options(): array
    {
        return [
            ['field' => 'id', 'label' => 'ID', 'width' => 90, 'minWidth' => 80, 'type' => 'numeric'],
            ['field' => 'order_number', 'label' => '№ заказа', 'width' => 110, 'minWidth' => 95, 'type' => null],
            ['field' => 'company_code', 'label' => 'Компания', 'width' => 110, 'minWidth' => 80, 'type' => null],
            ['field' => 'manager_id', 'label' => 'ID менеджера', 'width' => 120, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'manager_name', 'label' => 'Менеджер', 'width' => 150, 'minWidth' => 140, 'type' => null],
            ['field' => 'site_id', 'label' => 'ID площадки', 'width' => 110, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'order_date', 'label' => 'Дата заявки', 'width' => 130, 'minWidth' => 110, 'type' => 'date'],
            ['field' => 'loading_date', 'label' => 'Дата погрузки', 'width' => 140, 'minWidth' => 120, 'type' => 'date'],
            ['field' => 'unloading_date', 'label' => 'Дата выгрузки', 'width' => 140, 'minWidth' => 120, 'type' => 'date'],
            ['field' => 'loading_point', 'label' => 'Погрузка', 'width' => 190, 'minWidth' => 140, 'type' => null],
            ['field' => 'unloading_point', 'label' => 'Выгрузка', 'width' => 190, 'minWidth' => 140, 'type' => null],
            ['field' => 'cargo_description', 'label' => 'Груз', 'width' => 220, 'minWidth' => 160, 'type' => null],
            ['field' => 'customer_id', 'label' => 'ID заказчика', 'width' => 120, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'customer_name', 'label' => 'Заказчик', 'width' => 180, 'minWidth' => 140, 'type' => null],
            ['field' => 'customer_rate', 'label' => 'Ставка клиента', 'width' => 150, 'minWidth' => 120, 'type' => 'numeric'],
            ['field' => 'customer_payment_form', 'label' => 'Форма оплаты клиента', 'width' => 170, 'minWidth' => 140, 'type' => null],
            ['field' => 'customer_payment_term', 'label' => 'Условия оплаты клиента', 'width' => 180, 'minWidth' => 150, 'type' => null],
            ['field' => 'carrier_id', 'label' => 'ID перевозчика', 'width' => 130, 'minWidth' => 110, 'type' => 'numeric'],
            ['field' => 'carrier_name', 'label' => 'Перевозчик', 'width' => 180, 'minWidth' => 140, 'type' => null],
            ['field' => 'carrier_rate', 'label' => 'Ставка перевозчика', 'width' => 170, 'minWidth' => 130, 'type' => 'numeric'],
            ['field' => 'carrier_payment_form', 'label' => 'Форма оплаты перевозчика', 'width' => 190, 'minWidth' => 150, 'type' => null],
            ['field' => 'carrier_payment_term', 'label' => 'Условия оплаты перевозчика', 'width' => 200, 'minWidth' => 160, 'type' => null],
            ['field' => 'driver_id', 'label' => 'ID водителя', 'width' => 110, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'additional_expenses', 'label' => 'Доп. расходы', 'width' => 150, 'minWidth' => 120, 'type' => 'numeric'],
            ['field' => 'insurance', 'label' => 'Страховка', 'width' => 130, 'minWidth' => 110, 'type' => 'numeric'],
            ['field' => 'bonus', 'label' => 'Бонус', 'width' => 120, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'delta', 'label' => 'Маржа', 'width' => 120, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'kpi_percent', 'label' => 'KPI %', 'width' => 100, 'minWidth' => 80, 'type' => 'numeric'],
            ['field' => 'salary_accrued', 'label' => 'ЗП начисл.', 'width' => 130, 'minWidth' => 110, 'type' => 'numeric'],
            ['field' => 'salary_paid', 'label' => 'ЗП выпл.', 'width' => 120, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'status', 'label' => 'Статус системы', 'width' => 140, 'minWidth' => 120, 'type' => null],
            ['field' => 'manual_status', 'label' => 'Статус вручную', 'width' => 150, 'minWidth' => 130, 'type' => null],
            ['field' => 'status_text', 'label' => 'Статус', 'width' => 130, 'minWidth' => 110, 'type' => null],
            ['field' => 'status_updated_by', 'label' => 'Статус обновил', 'width' => 140, 'minWidth' => 120, 'type' => 'numeric'],
            ['field' => 'status_updated_at', 'label' => 'Статус обновлен', 'width' => 150, 'minWidth' => 130, 'type' => 'datetime'],
            ['field' => 'is_active', 'label' => 'Активен', 'width' => 100, 'minWidth' => 90, 'type' => 'boolean'],
            ['field' => 'ai_draft_id', 'label' => 'ID AI черновика', 'width' => 140, 'minWidth' => 120, 'type' => 'numeric'],
            ['field' => 'ai_confidence', 'label' => 'AI confidence', 'width' => 130, 'minWidth' => 110, 'type' => 'numeric'],
            ['field' => 'ai_metadata', 'label' => 'AI metadata', 'width' => 190, 'minWidth' => 160, 'type' => 'json'],
            ['field' => 'ati_response', 'label' => 'ATI response', 'width' => 190, 'minWidth' => 160, 'type' => 'json'],
            ['field' => 'ati_load_id', 'label' => 'ATI load ID', 'width' => 150, 'minWidth' => 120, 'type' => null],
            ['field' => 'ati_published_at', 'label' => 'ATI опубликован', 'width' => 160, 'minWidth' => 130, 'type' => 'datetime'],
            ['field' => 'invoice_number', 'label' => 'Счет', 'width' => 130, 'minWidth' => 100, 'type' => null],
            ['field' => 'upd_number', 'label' => 'УПД', 'width' => 120, 'minWidth' => 90, 'type' => null],
            ['field' => 'waybill_number', 'label' => 'ТТН', 'width' => 120, 'minWidth' => 90, 'type' => null],
            ['field' => 'track_number_customer', 'label' => 'Трек заказчика', 'width' => 160, 'minWidth' => 130, 'type' => null],
            ['field' => 'track_sent_date_customer', 'label' => 'Трек заказчику отправлен', 'width' => 200, 'minWidth' => 160, 'type' => 'date'],
            ['field' => 'track_received_date_customer', 'label' => 'Закрывашки заказчиком получены', 'width' => 230, 'minWidth' => 190, 'type' => 'date'],
            ['field' => 'track_number_carrier', 'label' => 'Трек перевозчика', 'width' => 170, 'minWidth' => 140, 'type' => null],
            ['field' => 'track_sent_date_carrier', 'label' => 'Трек перевозчику отправлен', 'width' => 220, 'minWidth' => 180, 'type' => 'date'],
            ['field' => 'track_received_date_carrier', 'label' => 'Закрывашки перевозчика получены', 'width' => 230, 'minWidth' => 190, 'type' => 'date'],
            ['field' => 'order_customer_number', 'label' => 'Номер заявки клиента', 'width' => 170, 'minWidth' => 140, 'type' => null],
            ['field' => 'order_customer_date', 'label' => 'Дата заявки клиента', 'width' => 170, 'minWidth' => 140, 'type' => 'date'],
            ['field' => 'order_carrier_number', 'label' => 'Номер заявки перевозчика', 'width' => 190, 'minWidth' => 150, 'type' => null],
            ['field' => 'order_carrier_date', 'label' => 'Дата заявки перевозчика', 'width' => 180, 'minWidth' => 150, 'type' => 'date'],
            ['field' => 'upd_carrier_number', 'label' => 'УПД перевозчика', 'width' => 160, 'minWidth' => 130, 'type' => null],
            ['field' => 'upd_carrier_date', 'label' => 'Дата УПД перевозчика', 'width' => 170, 'minWidth' => 140, 'type' => 'date'],
            ['field' => 'customer_contact_name', 'label' => 'Контакт заказчика', 'width' => 170, 'minWidth' => 140, 'type' => null],
            ['field' => 'customer_contact_phone', 'label' => 'Телефон заказчика', 'width' => 170, 'minWidth' => 140, 'type' => null],
            ['field' => 'customer_contact_email', 'label' => 'Email заказчика', 'width' => 180, 'minWidth' => 150, 'type' => null],
            ['field' => 'carrier_contact_name', 'label' => 'Контакт перевозчика', 'width' => 180, 'minWidth' => 150, 'type' => null],
            ['field' => 'carrier_contact_phone', 'label' => 'Телефон перевозчика', 'width' => 180, 'minWidth' => 150, 'type' => null],
            ['field' => 'carrier_contact_email', 'label' => 'Email перевозчика', 'width' => 190, 'minWidth' => 160, 'type' => null],
            ['field' => 'created_by', 'label' => 'Создал', 'width' => 110, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'updated_by', 'label' => 'Изменил', 'width' => 110, 'minWidth' => 100, 'type' => 'numeric'],
            ['field' => 'metadata', 'label' => 'Metadata', 'width' => 180, 'minWidth' => 150, 'type' => 'json'],
            ['field' => 'payment_statuses', 'label' => 'Статусы оплат', 'width' => 180, 'minWidth' => 150, 'type' => 'json'],
            ['field' => 'created_at', 'label' => 'Создан', 'width' => 150, 'minWidth' => 130, 'type' => 'datetime'],
            ['field' => 'updated_at', 'label' => 'Обновлен', 'width' => 150, 'minWidth' => 130, 'type' => 'datetime'],
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
        $defaultFields = [
            'order_number',
            'company_code',
            'manager_name',
            'order_date',
            'loading_point',
            'unloading_point',
            'loading_date',
            'unloading_date',
            'cargo_description',
            'customer_name',
            'carrier_name',
            'customer_rate',
            'carrier_rate',
            'additional_expenses',
            'delta',
            'kpi_percent',
            'salary_paid',
            'status_text',
            'invoice_number',
            'upd_number',
            'waybill_number',
        ];

        return match ($roleName) {
            'manager' => array_values(array_filter($defaultFields, fn (string $field): bool => $field !== 'salary_paid')),
            default => $defaultFields,
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
