<?php

return [
    'enabled' => (bool) env('FCM_ENABLED', false),

    'project_id' => env('FCM_PROJECT_ID'),

    /**
     * Service account JSON path or inline JSON string for HTTP v1 API.
     */
    'credentials' => env('FCM_CREDENTIALS'),

    'default_android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'crm_chat_messages'),
];
