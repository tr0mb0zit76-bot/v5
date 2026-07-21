<?php

namespace App\Enums;

enum OrderClaimType: string
{
    case Idle = 'idle';
    case Damage = 'damage';
    case Late = 'late';
    case RateDispute = 'rate_dispute';
    case Documents = 'documents';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Простой',
            self::Damage => 'Порча / недостача',
            self::Late => 'Срыв срока',
            self::RateDispute => 'Спор по ставке',
            self::Documents => 'Документы',
            self::Other => 'Иное',
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
