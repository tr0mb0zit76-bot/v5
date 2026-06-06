<?php

return [

    'model_version' => env('CONTRACTOR_SCORING_MODEL_VERSION', '2.0'),

    /*
    | Пороги выручки (₽, последний год Checko) для tier.
    */
    'tier_thresholds_rub' => [
        'small' => 20_000_000,
        'mid' => 200_000_000,
        'large' => 1_000_000_000,
        'enterprise' => 5_000_000_000,
    ],

    'tier_labels' => [
        'micro' => 'Микро',
        'small' => 'Малый',
        'mid' => 'Средний',
        'large' => 'Крупный',
        'enterprise' => 'Крупнейший',
    ],

    /*
    | Базовый ориентир лимита задолженности до health-множителя (₽).
    */
    'tier_base_debt_limit_rub' => [
        'micro' => 150_000,
        'small' => 600_000,
        'mid' => 1_500_000,
        'large' => 6_000_000,
        'enterprise' => 20_000_000,
    ],

    'component_weights' => [
        'legal' => 0.40,
        'capacity' => 0.40,
        'relationship' => 0.20,
    ],

    /*
    | Не выше доли от выручки при известной выручке.
    */
    'revenue_cap_ratio' => 0.08,

    'limit_round_step_rub' => 50_000,

    'max_recommended_postpayment_days' => 10,

];
