<?php

namespace App\Enums;

enum TrainerAiRole: string
{
    case Client = 'client';

    /** Менеджер играет клиента и формулирует возражения; ИИ — продавец. */
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'ИИ клиент',
            self::Seller => 'ИИ продавец',
        };
    }
}
