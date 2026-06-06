<?php

return [

    'base_url' => rtrim((string) env('NTFY_BASE_URL', ''), '/'),

    'default_priority' => (int) env('NTFY_DEFAULT_PRIORITY', 3),

    'timeout_seconds' => (int) env('NTFY_TIMEOUT_SECONDS', 5),

];
