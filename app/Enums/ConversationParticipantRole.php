<?php

namespace App\Enums;

enum ConversationParticipantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::Admin => 'Администратор',
            self::Member => 'Участник',
        };
    }
}
