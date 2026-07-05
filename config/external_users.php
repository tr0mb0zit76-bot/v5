<?php

return [
    'invite_ttl_days' => (int) env('EXTERNAL_USER_INVITE_TTL_DAYS', 14),

    'apk_url' => env('MOBILE_APP_APK_URL', '/downloads/traklo.apk'),
];
