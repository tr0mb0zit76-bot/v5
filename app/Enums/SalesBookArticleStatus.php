<?php

namespace App\Enums;

enum SalesBookArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликовано',
        };
    }
}
