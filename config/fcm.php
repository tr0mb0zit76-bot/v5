<?php

return [
    'enabled' => (bool) env('FCM_ENABLED', false),

    'project_id' => env('FCM_PROJECT_ID'),

    /**
     * Service account JSON path or inline JSON string for HTTP v1 API.
     */
    'credentials' => env('FCM_CREDENTIALS'),

    /**
     * Только для тестов / отладки: пропустить OAuth service account.
     */
    'access_token_override' => env('FCM_ACCESS_TOKEN_OVERRIDE'),

    'default_android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'crm_chat_messages'),

    /**
     * Cabinet notification kinds that trigger FCM push to registered mobile devices.
     *
     * @var list<string>
     */
    'push_kinds' => [
        'chat_message',
        'order_document_approval',
        'order_document_approved',
        'order_closing_documents_required',
        'contractor_limit_approval',
    ],

    /**
     * Android notification channel id per kind (channels created in MainActivity).
     *
     * @var array<string, string>
     */
    'android_channels' => [
        'chat_message' => 'crm_chat_messages',
        'order_document_approval' => 'crm_orders',
        'order_document_approved' => 'crm_orders',
        'order_closing_documents_required' => 'crm_accounting',
        'contractor_limit_approval' => 'crm_orders',
    ],
];
