<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Транспортные документы: бумажная группа (ТН/CMR/ТСД) и ЭТрН отдельно в ЭПД.
 */
final class OrderDocumentTransportTypes
{
    /** @var list<string> */
    public const VALUES = ['waybill', 'etrn', 'cmr'];

    /** @var list<string> */
    public const PAPER_VALUES = ['waybill', 'cmr'];

    /** @var list<string> */
    public const EPD_VALUES = ['etrn', 'expedition_receipt'];

    public const UNIFIED_LABEL = 'ТН / CMR / ТСД';

    public const ETRN_LABEL = 'ЭТрН';

    public const EXPEDITION_RECEIPT_LABEL = 'Экспедиторская расписка';

    public static function isTransportType(?string $type): bool
    {
        return in_array((string) $type, self::VALUES, true);
    }

    public static function isPaperTransportType(?string $type): bool
    {
        return in_array((string) $type, self::PAPER_VALUES, true);
    }

    public static function displayLabel(?string $type): ?string
    {
        $normalized = (string) $type;

        if (self::isPaperTransportType($normalized)) {
            return self::UNIFIED_LABEL;
        }

        if ($normalized === 'etrn') {
            return self::ETRN_LABEL;
        }

        if ($normalized === 'expedition_receipt') {
            return self::EXPEDITION_RECEIPT_LABEL;
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        return [
            ['value' => 'waybill', 'label' => self::UNIFIED_LABEL],
            ['value' => 'etrn', 'label' => self::ETRN_LABEL],
            ['value' => 'expedition_receipt', 'label' => self::EXPEDITION_RECEIPT_LABEL],
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
            ['value' => 'etrn', 'label' => self::ETRN_LABEL],
            ['value' => 'cmr', 'label' => 'CMR'],
        ];
    }
}
