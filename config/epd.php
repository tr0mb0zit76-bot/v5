<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ГИС ЭПД / ИС ЭПД (ЭТрН и др.)
    |--------------------------------------------------------------------------
    |
    | После выбора сертифицированного оператора электронных перевозочных документов
    | укажите драйвер и базовый URL API. Пока интеграция не подключена, в CRM можно
    | фиксировать ЭТрН как документ заказа (type = etrn) и хранить ссылки в metadata.
    |
    | План (не зафиксирован договором): оператор ИС ЭПД — АО «Калуга Астрал».
    | Ориентиры по продукту и подключению к ГИС см. в planned_operator.urls ниже.
    |
    */

    /*
     * Ожидаемый оператор для будущей реализации драйвера (slug — для кода, не для API).
     *
     * @var array{code: string, name: string, urls: list<array{label: string, href: string}>}
     */
    'planned_operator' => [
        'code' => 'kaluga_astral',
        'name' => 'АО «Калуга Астрал»',
        'urls' => [
            [
                'label' => 'API ЭПД (обзор на сайте Астрал)',
                'href' => 'https://astral.ru/corporate/api-epd/',
            ],
            [
                'label' => 'Подключение к ГИС ЭП (портал is.astral.ru)',
                'href' => 'https://is.astral.ru/services/podklyuchenie-k-gis/podklyuchenie-k-gis-ep/',
            ],
        ],
    ],

    'operator' => [
        'driver' => env('EPD_OPERATOR_DRIVER', 'null'),
        'base_url' => env('EPD_OPERATOR_BASE_URL'),
        'webhook_secret' => env('EPD_OPERATOR_WEBHOOK_SECRET'),
    ],

    'integration' => [
        'one_c_fresh_token' => env('EPD_1C_FRESH_TOKEN'),
    ],

];
