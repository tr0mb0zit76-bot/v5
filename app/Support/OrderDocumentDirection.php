<?php

namespace App\Support;

use App\Models\OrderDocument;

/**
 * Направление файла в реестре заказа:
 * - incoming — скан/ЭДО от контрагента (закрывает чек-лист);
 * - outgoing — исходящий от нас (счёт/закрывающие из 1С к отправке, не закрывает слоты).
 */
final class OrderDocumentDirection
{
    public const INCOMING = 'incoming';

    public const OUTGOING = 'outgoing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::INCOMING, self::OUTGOING];
    }

    public static function normalize(?string $value): string
    {
        return $value === self::OUTGOING ? self::OUTGOING : self::INCOMING;
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     */
    public static function fromDocument(OrderDocument|array $document): string
    {
        if ($document instanceof OrderDocument) {
            return self::normalize(
                is_string(data_get($document->metadata, 'direction'))
                    ? (string) data_get($document->metadata, 'direction')
                    : null
            );
        }

        $direct = $document['direction'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return self::normalize($direct);
        }

        $nested = data_get($document, 'metadata.direction');

        return self::normalize(is_string($nested) ? $nested : null);
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     */
    public static function isOutgoing(OrderDocument|array $document): bool
    {
        return self::fromDocument($document) === self::OUTGOING;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::INCOMING, 'label' => 'Входящий (от контрагента)'],
            ['value' => self::OUTGOING, 'label' => 'Исходящий (от нас)'],
        ];
    }

    public static function label(string $direction): string
    {
        return match (self::normalize($direction)) {
            self::OUTGOING => 'Исходящий',
            default => 'Входящий',
        };
    }
}
