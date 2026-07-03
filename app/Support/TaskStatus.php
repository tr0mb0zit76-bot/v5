<?php

namespace App\Support;

final class TaskStatus
{
    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'new' => 'Новая',
        'in_progress' => 'В работе',
        'review' => 'На проверке',
        'done' => 'Завершена',
        'on_hold' => 'Отложена',
        'cancelled' => 'Отменена',
    ];

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(self::STATUSES),
            array_values(self::STATUSES),
        );
    }

    public static function label(string $value): string
    {
        return self::STATUSES[$value] ?? $value;
    }

    public static function leadStatusByTaskStatus(string $value): ?string
    {
        return match ($value) {
            'new' => 'new',
            'in_progress' => 'qualification',
            'review' => 'negotiation',
            'done' => null,
            'on_hold' => 'on_hold',
            'cancelled' => null,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return ['new', 'in_progress', 'review', 'on_hold'];
    }
}
