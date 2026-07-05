<?php

return [
    'force_config' => (bool) env('MOBILE_APP_FORCE_CONFIG', false),
    'manifest_path' => env('MOBILE_APP_MANIFEST_PATH', public_path('downloads/traklo-update.json')),
    'latest_version_code' => (int) env('MOBILE_APP_LATEST_VERSION_CODE', 1),
    'latest_version_name' => env('MOBILE_APP_LATEST_VERSION_NAME', '1.0'),
    'min_supported_version_code' => (int) env('MOBILE_APP_MIN_SUPPORTED_VERSION_CODE', 1),
    'apk_url' => env('MOBILE_APP_APK_URL', '/downloads/traklo.apk'),
    'changelog' => env('MOBILE_APP_CHANGELOG', 'Обновление Traklo доступно для установки.'),
];
