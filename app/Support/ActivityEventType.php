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
    case OrderIntakeApplied = 'order_intake_applied';
    case CloseOutcomeRecorded = 'close_outcome_recorded';
    case PortraitInsightAccepted = 'portrait_insight_accepted';

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
            self::OrderIntakeApplied => 'Заявка → заказ',
            self::CloseOutcomeRecorded => 'Причина закрытия',
            self::PortraitInsightAccepted => 'Факт в портрет',
        };
    }
}
