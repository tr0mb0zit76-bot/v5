<?php

namespace App\Support;

final class ContractorWorkStatus
{
    public const ACTIVE = 'active';

    public const IN_DEVELOPMENT = 'in_development';

    public const WORK_BAN = 'work_ban';

    public const WORK_PAUSE = 'work_pause';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::IN_DEVELOPMENT,
            self::WORK_BAN,
            self::WORK_PAUSE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function manualValues(): array
    {
        return [
            self::ACTIVE,
            self::IN_DEVELOPMENT,
            self::WORK_BAN,
        ];
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::IN_DEVELOPMENT => 'В разработке',
            self::WORK_BAN => 'Запрет на работу',
            self::WORK_PAUSE => 'Пауза в работе',
            default => 'Активен',
        };
    }

    /**
     * @return array{badge: string, text: string}
     */
    public static function badgeClasses(?string $status, bool $isArchived = false): array
    {
        if ($isArchived) {
            return [
                'badge' => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
                'text' => 'Архив',
            ];
        }

        return match ($status) {
            self::IN_DEVELOPMENT => [
                'badge' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200',
                'text' => self::label($status),
            ],
            self::WORK_BAN => [
                'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
                'text' => self::label($status),
            ],
            self::WORK_PAUSE => [
                'badge' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
                'text' => self::label($status),
            ],
            default => [
                'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                'text' => self::label(self::ACTIVE),
            ],
        };
    }
}
