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
    | public_path — относительно каталога public/, например documents/sla/customer-offer.pdf
    | panel — customers | carriers
    |
    */
    'sla_documents' => [
        'customer-offer' => [
            'panel' => 'customers',
            'label' => 'Договор-оферта',
            'public_path' => env('SHOWCASE_SLA_CUSTOMER_OFFER_PUBLIC', 'documents/sla/customer-offer.pdf'),
        ],
        'carrier-offer' => [
            'panel' => 'carriers',
            'label' => 'Договор-оферта',
            'public_path' => env('SHOWCASE_SLA_CARRIER_OFFER_PUBLIC', 'documents/sla/carrier-offer.pdf'),
        ],
    ],

];
