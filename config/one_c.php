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
        'bank_incoming_path' => (string) env(
            'ONE_C_ODATA_BANK_INCOMING_PATH',
            '/odata/standard.odata/Document_ПоступлениеНаРасчетныйСчет'
        ),
        'bank_outgoing_path' => (string) env(
            'ONE_C_ODATA_BANK_OUTGOING_PATH',
            '/odata/standard.odata/Document_СписаниеСРасчетногоСчета'
        ),
        'buyer_invoice_path' => (string) env(
            'ONE_C_ODATA_BUYER_INVOICE_PATH',
            '/odata/standard.odata/Document_СчетНаОплатуПокупателю'
        ),
    ],

    /**
     * Счёт CRM (management_bank_accounts.account_number) для импорта банка из 1С.
     * По умолчанию — Сбер Автоальянс-Смоленск. Легаси; предпочтительно publications.*.bank_account_number.
     */
    'bank_statement' => [
        'account_number' => (string) env('ONE_C_BANK_ACCOUNT_NUMBER', '40702810959710001997'),
    ],

    /** Код публикации по умолчанию (см. publications). */
    'default_publication' => (string) env('ONE_C_DEFAULT_PUBLICATION', 'autalliance'),

    /**
     * Ответственный за эскалации моста (assignee задач). Initiator = system|user отдельно.
     */
    'bridge' => [
        'escalation_user_id' => env('ONE_C_BRIDGE_ESCALATION_USER_ID') !== null
            ? (int) env('ONE_C_BRIDGE_ESCALATION_USER_ID')
            : null,
        'pending_attention_min' => (int) env('ONE_C_BRIDGE_PENDING_ATTENTION_MIN', 1),
    ],

    /**
     * Токен CRM:… в назначении/номенклатуре для однозначного матчинга.
     * enforce_outgoing_bank — стоп фиксации банковского платежа перевозчику без токена.
     */
    'payment_token' => [
        'enforce_outgoing_bank' => filter_var(
            env('ONE_C_ENFORCE_OUTGOING_PAYMENT_TOKEN', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /**
     * Публикации ИБ по юрлицам. Общие ONE_C_USERNAME / ONE_C_PASSWORD.
     * date_filter_mode=client — если OData ИБ падает на Date+AUTOORDER (Гросс/Профсфера).
     */
    'publications' => [
        'autalliance' => [
            'label' => 'Автоальянс-Смоленск',
            'base_url' => rtrim((string) env(
                'ONE_C_AUTALLIANCE_BASE_URL',
                (string) env('ONE_C_BASE_URL', 'https://avtoalyns-crm.case-it.ru/Avtoalians_4nYnMmRSab')
            ), '/'),
            'organization_ref' => (string) env(
                'ONE_C_AUTALLIANCE_ORG_REF',
                (string) env('ONE_C_ORGANIZATION_REF', '19b37fca-5d84-11f1-8bf4-fa163ea037a3')
            ),
            'organization_inn' => (string) env('ONE_C_AUTALLIANCE_ORG_INN', '6732110940'),
            'bank_account_number' => (string) env(
                'ONE_C_AUTALLIANCE_BANK_ACCOUNT',
                (string) env('ONE_C_BANK_ACCOUNT_NUMBER', '40702810959710001997')
            ),
            'service_nomenclature_ref' => (string) env(
                'ONE_C_AUTALLIANCE_SERVICE_NOMENCLATURE_REF',
                (string) env('ONE_C_SERVICE_NOMENCLATURE_REF', '9ec829b8-632e-11f1-8745-fa163ea037a3')
            ),
            'service_nomenclature_code' => (string) env(
                'ONE_C_AUTALLIANCE_SERVICE_NOMENCLATURE_CODE',
                (string) env('ONE_C_SERVICE_NOMENCLATURE_CODE', '00-00000001')
            ),
            'date_filter_mode' => 'odata',
            'enabled' => filter_var(env('ONE_C_AUTALLIANCE_ENABLED', true), FILTER_VALIDATE_BOOL),
        ],
        'gross' => [
            'label' => 'Гросс',
            'base_url' => rtrim((string) env(
                'ONE_C_GROSS_BASE_URL',
                'https://avtoalyns-crm.case-it.ru/Gross_44N8sTPEXf'
            ), '/'),
            'organization_ref' => (string) env(
                'ONE_C_GROSS_ORG_REF',
                '13d87b6e-bae2-11ef-89a3-dc68443ee9e4'
            ),
            'organization_inn' => (string) env('ONE_C_GROSS_ORG_INN', '6345031755'),
            'bank_account_number' => (string) env(
                'ONE_C_GROSS_BANK_ACCOUNT',
                '40702810629940001726'
            ),
            'service_nomenclature_ref' => (string) env('ONE_C_GROSS_SERVICE_NOMENCLATURE_REF', ''),
            'service_nomenclature_code' => (string) env('ONE_C_GROSS_SERVICE_NOMENCLATURE_CODE', ''),
            'date_filter_mode' => 'client',
            'enabled' => filter_var(env('ONE_C_GROSS_ENABLED', true), FILTER_VALIDATE_BOOL),
        ],
        'profsfera' => [
            'label' => 'Профсфера',
            'base_url' => rtrim((string) env(
                'ONE_C_PROFSFERA_BASE_URL',
                'https://avtoalyns-crm.case-it.ru/ProSfera_gRLXXFMK8M'
            ), '/'),
            'organization_ref' => (string) env(
                'ONE_C_PROFSFERA_ORG_REF',
                '68778110-58ca-11f1-8af0-fa163eafb81d'
            ),
            'organization_inn' => (string) env('ONE_C_PROFSFERA_ORG_INN', '6321213940'),
            'bank_account_number' => (string) env(
                'ONE_C_PROFSFERA_BANK_ACCOUNT',
                '40702810508470000001'
            ),
            // В ИБ Профсфера ТЭУ = 00-00000002 (00-00000001 — помещение).
            'service_nomenclature_ref' => (string) env(
                'ONE_C_PROFSFERA_SERVICE_NOMENCLATURE_REF',
                'af537684-63c4-11f1-8ae7-fa163eafb81d'
            ),
            'service_nomenclature_code' => (string) env(
                'ONE_C_PROFSFERA_SERVICE_NOMENCLATURE_CODE',
                '00-00000002'
            ),
            'date_filter_mode' => 'client',
            'enabled' => filter_var(env('ONE_C_PROFSFERA_ENABLED', true), FILTER_VALIDATE_BOOL),
        ],
    ],
];
