<?php

namespace App\Support;

enum CommercialNudgeType: string
{
    case NoReply = 'no_reply';
    case StageOverdue = 'stage_overdue';
    case NextContactMissed = 'next_contact_missed';
    case LedgerIdle = 'ledger_idle';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    public function label(): string
    {
        return (string) config('commercial_nudges.types.'.$this->value.'.label', $this->value);
    }

    public function metaKey(): string
    {
        return (string) config('commercial_nudges.types.'.$this->value.'.meta_key', 'commercial_nudge_'.$this->value);
    }

    public function defaultPriority(): string
    {
        return (string) config('commercial_nudges.types.'.$this->value.'.default_priority', 'medium');
    }
}
