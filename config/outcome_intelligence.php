<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outcome Intelligence — пороги сигналов (фаза 3.5)
    |--------------------------------------------------------------------------
    |
    | idle_dwell: долго на этапе при малой активности (не путать с «глубокой работой»).
    | stage_name_patterns: этапы для отдельного анализа (квалификация и т.п.).
    |
    */

    'idle_dwell_min_days' => (float) env('OUTCOME_IDLE_DWELL_MIN_DAYS', 2),

    'idle_dwell_max_activity_events' => (int) env('OUTCOME_IDLE_DWELL_MAX_ACTIVITY', 1),

    'qualification_stage_name_patterns' => ['квалиф', 'qualification'],

    'coaching_default_days' => (int) env('OUTCOME_COACHING_DEFAULT_DAYS', 90),

    'coaching_sample_limit' => (int) env('OUTCOME_COACHING_SAMPLE_LIMIT', 10),

];
