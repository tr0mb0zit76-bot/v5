<?php

namespace App\Support;

enum ExternalParty: string
{
    case Carrier = 'carrier';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Carrier => 'Перевозчик',
            self::Customer => 'Заказчик',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
