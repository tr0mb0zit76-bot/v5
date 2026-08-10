<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Коннектор CRM ↔ 1С БП (on-prem)
    |--------------------------------------------------------------------------
    |
    | MVP: кнопка в заказе → Document.РеализацияТоваровУслуг (услуги).
    | driver=fake — без живой 1С (локалка/тесты); http — OData/HTTP к публикации ИБ.
    |
    */

    'enabled' => (bool) env('ONE_C_ENABLED', false),

    /** fake | http */
    'driver' => env('ONE_C_DRIVER', 'fake'),

    'base_url' => rtrim((string) env('ONE_C_BASE_URL', ''), '/'),

    'username' => (string) env('ONE_C_USERNAME', ''),

    'password' => (string) env('ONE_C_PASSWORD', ''),

    'timeout_seconds' => (int) env('ONE_C_TIMEOUT_SECONDS', 30),

    /**
     * Имена допреквизитов в 1С (пусто = не отправлять; в типовой ИБ их нет).
     */
    'extra_attributes' => [
        'order_id' => (string) env('ONE_C_ATTR_ORDER_ID', ''),
        'order_number' => (string) env('ONE_C_ATTR_ORDER_NUMBER', ''),
    ],

    /**
     * Номенклатура услуги перевозки в 1С (код или GUID Ref).
     * По умолчанию — «ТЭУ»; содержание строки = сводка делопроизводителя.
     */
    'service_nomenclature' => [
        'code' => env('ONE_C_SERVICE_NOMENCLATURE_CODE'),
        'ref' => env('ONE_C_SERVICE_NOMENCLATURE_REF'),
        'content_template' => (string) env(
            'ONE_C_SERVICE_CONTENT_TEMPLATE',
            'Транспортные услуги по заказу {order_number}'
        ),
    ],

    /**
     * Организация в 1С (если в ИБ несколько). Пусто — выбирает сторона 1С/HTTP-метод.
     */
    'organization_ref' => env('ONE_C_ORGANIZATION_REF'),

    /** Валюта документа (Ref_Key справочника Валюты), обычно руб. */
    'currency_ref' => env('ONE_C_CURRENCY_REF'),

    'odata' => [
        /** Относительный путь к сущности OData (настраивается под публикацию). */
        'realization_path' => (string) env(
            'ONE_C_ODATA_REALIZATION_PATH',
            '/odata/standard.odata/Document_РеализацияТоваровУслуг'
        ),
        'counterparty_path' => (string) env(
            'ONE_C_ODATA_COUNTERPARTY_PATH',
            '/odata/standard.odata/Catalog_Контрагенты'
        ),
    ],
];
