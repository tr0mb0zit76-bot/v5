<?php

return [
    'analytics' => [
        'default_days' => (int) env('SALES_SCRIPT_ANALYTICS_DAYS', 30),
        'min_sample_size' => (int) env('SALES_SCRIPT_ANALYTICS_MIN_SAMPLE', 10),
        'success_outcomes' => ['progress', 'quote_sent', 'won'],
    ],
];
