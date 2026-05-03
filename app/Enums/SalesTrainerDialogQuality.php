<?php

namespace App\Enums;

enum SalesTrainerDialogQuality: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Stuck = 'stuck';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Диалог успешный',
            self::Failure => 'Диалог неудачный',
            self::Stuck => 'Зашёл в тупик',
        };
    }
}
