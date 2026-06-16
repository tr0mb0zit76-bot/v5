<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Транспортные документы (ТН, ЭТрН, CMR, ТСД) — одна группа в реестре и чек-листе.
 */
final class OrderDocumentTransportTypes
{
    /** @var list<string> */
    public const VALUES = ['waybill', 'etrn', 'cmr'];

    public const UNIFIED_LABEL = 'ТН / ЭТрН / CMR / ТСД';

    public static function isTransportType(?string $type): bool
    {
        return in_array((string) $type, self::VALUES, true);
    }

    public static function displayLabel(?string $type): ?string
    {
        if (! self::isTransportType($type)) {
            return null;
        }

        return self::UNIFIED_LABEL;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return [
            ['value' => 'waybill', 'label' => self::UNIFIED_LABEL],
        ];
    }

    /**
     * Подтипы для редактирования уже загруженного файла (значение в БД остаётся waybill|etrn|cmr).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function subtypeSelectOptions(): array
    {
        return [
            ['value' => 'waybill', 'label' => 'Бумажная ТН'],
            ['value' => 'etrn', 'label' => 'ЭТрН'],
            ['value' => 'cmr', 'label' => 'CMR'],
        ];
    }
}
