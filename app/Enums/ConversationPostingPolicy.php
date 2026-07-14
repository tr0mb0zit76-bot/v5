<?php

namespace App\Enums;

enum ConversationPostingPolicy: string
{
    case Members = 'members';
    case Admins = 'admins';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Пишут все участники',
            self::Admins => 'Пишут владелец и администраторы',
            self::Owner => 'Пишет только владелец',
        };
    }
}
