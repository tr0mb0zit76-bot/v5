<?php

return [
    'driver' => env('DOCUMENT_STORAGE', 'local'),

    'nextcloud' => [
        'base_url' => env('NEXTCLOUD_BASE_URL'),
        'webdav_user' => env('NEXTCLOUD_WEBDAV_USER'),
        'webdav_password' => env('NEXTCLOUD_WEBDAV_PASSWORD'),
        'webdav_root' => env('NEXTCLOUD_WEBDAV_ROOT', '/remote.php/dav/files'),
        'timeout' => (int) env('NEXTCLOUD_TIMEOUT', 30),
        // false — только для локальной отладки (самоподписанный/чужой CN). В проде нужен валидный TLS для NEXTCLOUD_BASE_URL.
        'verify_ssl' => filter_var(env('NEXTCLOUD_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],
];
