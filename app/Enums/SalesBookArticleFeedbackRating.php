<?php

namespace App\Enums;

enum SalesBookArticleFeedbackRating: string
{
    case Helpful = 'helpful';
    case Unclear = 'unclear';
    case Outdated = 'outdated';

    public function label(): string
    {
        return match ($this) {
            self::Helpful => 'Полезно',
            self::Unclear => 'Непонятно',
            self::Outdated => 'Устарело',
        };
    }
}
