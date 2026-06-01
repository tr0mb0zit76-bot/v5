<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Витрина: видимость разделов
    |--------------------------------------------------------------------------
    */
    'cases_nav_visible' => filter_var(env('SHOWCASE_CASES_NAV_VISIBLE', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | SLA: договоры-оферты (файлы в public/)
    |--------------------------------------------------------------------------
    |
    | public_path — относительно каталога public/, например showcase-sla/customer-offer.pdf
    | (не используйте public/documents/ — URL /documents занят модулем CRM, nginx отдаёт каталог с редиректом на http)
    | panel — customers | carriers
    |
    */
    'sla_documents' => [
        'customer-offer' => [
            'panel' => 'customers',
            'label' => 'Договор-оферта',
            'public_path' => env('SHOWCASE_SLA_CUSTOMER_OFFER_PUBLIC', 'showcase-sla/customer-offer.pdf'),
        ],
        'carrier-offer' => [
            'panel' => 'carriers',
            'label' => 'Договор-оферта',
            'public_path' => env('SHOWCASE_SLA_CARRIER_OFFER_PUBLIC', 'showcase-sla/carrier-offer.pdf'),
        ],
    ],

];
