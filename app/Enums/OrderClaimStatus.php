<?php

namespace App\Enums;

enum OrderClaimStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InReview = 'in_review';
    case Negotiating = 'negotiating';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Open => 'Открыта',
            self::InReview => 'На рассмотрении',
            self::Negotiating => 'Переговоры',
            self::Resolved => 'Решена',
            self::Rejected => 'Отклонена',
            self::WrittenOff => 'Списана',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Rejected, self::WrittenOff], true);
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
