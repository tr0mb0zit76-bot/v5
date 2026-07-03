<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Approval notification routing
    |--------------------------------------------------------------------------
    */

    'approval_include_admins' => (bool) env('NOTIFICATIONS_APPROVAL_INCLUDE_ADMINS', false),

    'approval_kinds' => [
        'order_document_approval',
        'contractor_limit_approval',
    ],

    'ntfy_kinds' => [
        'order_document_approval',
        'contractor_limit_approval',
        'chat_message',
    ],

    'ntfy_enabled' => (bool) env('NTFY_ENABLED', false),

];
