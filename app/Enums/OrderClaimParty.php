<?php

namespace App\Enums;

enum OrderClaimParty: string
{
    case Customer = 'customer';
    case Carrier = 'carrier';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Заказчик',
            self::Carrier => 'Перевозчик',
            self::Internal => 'Внутренняя',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
