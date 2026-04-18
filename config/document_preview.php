<?php

use App\Support\DocumentPreview;

return [
    'driver' => DocumentPreview::resolvedDriverFromEnv(),

    'gotenberg' => [
        'url' => env('GOTENBERG_URL'),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 60),
    ],
];
