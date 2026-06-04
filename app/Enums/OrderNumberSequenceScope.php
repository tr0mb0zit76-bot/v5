<?php

namespace App\Enums;

enum OrderNumberSequenceScope: string
{
    case Global = 'global';
    case Year = 'year';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Сквозной (без сброса)',
            self::Year => 'Новый счёт каждый год',
            self::Month => 'Новый счёт каждый месяц',
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
