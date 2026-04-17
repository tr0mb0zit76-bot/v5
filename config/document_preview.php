<?php

return [
    'driver' => env('DOC_PREVIEW_DRIVER', 'html'),

    'gotenberg' => [
        'url' => env('GOTENBERG_URL'),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 60),
    ],
];
