<?php

namespace App\Support;

enum ActivityEventType: string
{
    case ProcessStageChanged = 'process_stage_changed';
    case OfferPrepared = 'offer_prepared';
    case OfferSent = 'offer_sent';
    case EmailOutbound = 'email_outbound';
    case EmailInbound = 'email_inbound';
    case TaskCreated = 'task_created';
    case NoteAdded = 'note_added';
    case LeadStatusChanged = 'lead_status_changed';
    case DispositionComment = 'disposition_comment';
    case CloseOutcomeRecorded = 'close_outcome_recorded';

    public function label(): string
    {
        return match ($this) {
            self::ProcessStageChanged => 'Этап процесса',
            self::OfferPrepared => 'КП подготовлено',
            self::OfferSent => 'КП отправлено',
            self::EmailOutbound => 'Исходящее письмо',
            self::EmailInbound => 'Входящее письмо',
            self::TaskCreated => 'Задача',
            self::NoteAdded => 'Заметка',
            self::LeadStatusChanged => 'Статус лида',
            self::DispositionComment => 'Диспозиция',
            self::CloseOutcomeRecorded => 'Причина закрытия',
        };
    }
}
