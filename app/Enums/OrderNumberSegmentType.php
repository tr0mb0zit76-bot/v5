<?php

namespace App\Enums;

enum OrderNumberSegmentType: string
{
    case Text = 'text';
    case Sequence = 'sequence';
    case Day = 'day';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Текст',
            self::Sequence => 'Автонумератор',
            self::Day => 'День месяца',
            self::Month => 'Месяц',
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
