<?php

use App\Support\CommercialNudgeType;

return [

    /*
    |--------------------------------------------------------------------------
    | Справочник типов напоминаний (nudges) по лидам
    |--------------------------------------------------------------------------
    */
    'types' => [
        CommercialNudgeType::NoReply->value => [
            'label' => 'Нет ответа на исходящее письмо',
            'description' => 'Создаёт задачу, если после отправки КП или письма клиент не ответил дольше порога этапа.',
            'meta_key' => 'commercial_nudge_no_reply',
            'legacy_meta_key' => 'commercial_offer_no_reply_nudge',
            'default_priority' => 'high',
        ],
        CommercialNudgeType::StageOverdue->value => [
            'label' => 'Просрочен этап воронки',
            'description' => 'Срабатывает, когда лид дольше норматива (duration_days) находится на этапе.',
            'meta_key' => 'commercial_nudge_stage_overdue',
            'default_priority' => 'high',
        ],
        CommercialNudgeType::NextContactMissed->value => [
            'label' => 'Пропущен следующий контакт',
            'description' => 'Дата next_contact_at на лиде прошла, контакт не состоялся.',
            'meta_key' => 'commercial_nudge_next_contact_missed',
            'default_priority' => 'medium',
        ],
        CommercialNudgeType::LedgerIdle->value => [
            'label' => 'Нет активности в ленте',
            'description' => 'На этапе нет событий Activity Ledger дольше заданного числа дней.',
            'meta_key' => 'commercial_nudge_ledger_idle',
            'default_priority' => 'medium',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Значения по умолчанию (если на этапе не задан nudge_triggers)
    |--------------------------------------------------------------------------
    */
    'default_triggers' => [
        CommercialNudgeType::NoReply->value,
        CommercialNudgeType::StageOverdue->value,
        CommercialNudgeType::NextContactMissed->value,
    ],

    'default_no_reply_days' => (int) env('COMMERCIAL_OFFER_NO_REPLY_NUDGE_DAYS', 3),

    'attention_queue_limit' => 15,

];
