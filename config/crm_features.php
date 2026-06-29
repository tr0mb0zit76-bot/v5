<?php

return [
    'commercial_mail_ai' => [
        'label' => 'AI в почте и лидах',
        'enabled' => env('CRM_FEATURE_COMMERCIAL_MAIL_AI', true),
        'depends' => ['mail'],
    ],
    'card_smart_links' => [
        'label' => 'Smart links на карточках',
        'enabled' => env('CRM_FEATURE_CARD_SMART_LINKS', true),
        'depends' => [],
    ],
    'order_document_mail' => [
        'label' => 'Отправка PDF документов заказа по e-mail',
        'enabled' => env('CRM_FEATURE_ORDER_DOCUMENT_MAIL', true),
        'depends' => ['mail', 'orders'],
    ],
];
