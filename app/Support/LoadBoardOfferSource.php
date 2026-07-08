<?php

namespace App\Support;

class LoadBoardOfferSource
{
    public const INTERNAL_CRM = 'internal_crm';

    public const ATI_MANUAL = 'ati_manual';

    public const PHONE = 'phone';

    public const EMAIL = 'email';

    public const MESSENGER = 'messenger';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::INTERNAL_CRM => 'CRM / база',
            self::ATI_MANUAL => 'ATI (вручную)',
            self::PHONE => 'Звонок',
            self::EMAIL => 'Почта',
            self::MESSENGER => 'Мессенджер',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::labels());
    }

    public static function label(?string $source): string
    {
        if ($source === null || $source === '') {
            return self::labels()[self::INTERNAL_CRM];
        }

        return self::labels()[$source] ?? $source;
    }
}
