<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checko.ru API (v2)
    |--------------------------------------------------------------------------
    |
    | Ключ и базовый URL задаются в .env; в репозитории секреты не храним.
    |
    */

    'api_key' => env('CHECKO_API_KEY'),

    'api_base' => rtrim(env('CHECKO_API_BASE', 'https://api.checko.ru/v2'), '/'),

    'cache_ttl_seconds' => (int) env('CHECKO_CACHE_TTL', 86400),

    'timeout_seconds' => (int) env('CHECKO_TIMEOUT', 20),

];
