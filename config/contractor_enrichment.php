<?php

return [
    'enabled' => (bool) env('CONTRACTOR_ENRICHMENT_ENABLED', true),

    'dispatch_on_create' => (bool) env('CONTRACTOR_ENRICHMENT_ON_CREATE', true),

    /** Hours between successful runs unless force=true */
    'throttle_hours' => (int) env('CONTRACTOR_ENRICHMENT_THROTTLE_HOURS', 12),

    /** CRM snapshot window */
    'window_months' => (int) env('CONTRACTOR_ENRICHMENT_WINDOW_MONTHS', 24),

    'recent_orders_limit' => 5,

    'web' => [
        'enabled' => (bool) env('CONTRACTOR_ENRICHMENT_WEB_ENABLED', true),
        'timeout_seconds' => 8,
        'max_snippets' => 5,
        'user_agent' => 'CRM-v5-ContractorEnrichment/1.0',
    ],

    /** DaData + Checko slim snapshot in dossier (reuse existing clients) */
    'include_external' => (bool) env('CONTRACTOR_ENRICHMENT_INCLUDE_EXTERNAL', true),
];
