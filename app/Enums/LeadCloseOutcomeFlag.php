<?php

namespace App\Enums;

enum LeadCloseOutcomeFlag: string
{
    case LostPrice = 'lost_price';
    case LostTiming = 'lost_timing';
    case LostCompetitor = 'lost_competitor';
    case LostNoNeed = 'lost_no_need';
    case LostWrongSegment = 'lost_wrong_segment';
    case LostNoAuthority = 'lost_no_authority';
    case LostGhosting = 'lost_ghosting';
    case LostInternal = 'lost_internal';
    case LostOther = 'lost_other';

    case WonRepeatClient = 'won_repeat_client';
    case WonPrice = 'won_price';
    case WonService = 'won_service';
    case WonUpsell = 'won_upsell';
    case WonOther = 'won_other';

    public function terminalOutcome(): string
    {
        return str_starts_with($this->value, 'lost_') ? 'lost' : 'won';
    }

    public function label(): string
    {
        return match ($this) {
            self::LostPrice => 'Цена / маржа',
            self::LostTiming => 'Срок / не успели',
            self::LostCompetitor => 'Ушли к конкуренту',
            self::LostNoNeed => 'Нет потребности',
            self::LostWrongSegment => 'Не наш сегмент',
            self::LostNoAuthority => 'Не вышли на ЛПР',
            self::LostGhosting => 'Пропал контакт',
            self::LostInternal => 'Внутренние причины',
            self::LostOther => 'Другое',
            self::WonRepeatClient => 'Повторный клиент',
            self::WonPrice => 'Выиграли по цене',
            self::WonService => 'Выиграли по сервису',
            self::WonUpsell => 'Апсейл / доп. услуги',
            self::WonOther => 'Другое',
        };
    }

    /**
     * @return list<self>
     */
    public static function forLost(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->terminalOutcome() === 'lost'));
    }

    /**
     * @return list<self>
     */
    public static function forWon(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->terminalOutcome() === 'won'));
    }
}
