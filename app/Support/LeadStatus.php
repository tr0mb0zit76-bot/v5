<?php

namespace App\Support;

final class LeadStatus
{
    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'new' => 'Новый',
        'qualification' => 'Квалификация',
        'calculation' => 'Просчёт',
        'proposal_ready' => 'КП подготовлено',
        'proposal_sent' => 'КП отправлено',
        'negotiation' => 'Переговоры',
        'won' => 'Конвертирован',
        'lost' => 'Закрыт без сделки',
        'on_hold' => 'Отложен',
    ];

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys(self::STATUSES), array_values(self::STATUSES));
    }

    public static function label(string $value): string
    {
        return self::STATUSES[$value] ?? $value;
    }
}
