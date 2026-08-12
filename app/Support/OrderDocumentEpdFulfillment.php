<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;

/**
 * ЭПД-слоты чек-листа: ЭТрН и экспедиторская расписка (файл или отметка ЭДО).
 */
final class OrderDocumentEpdFulfillment
{
    /** @var list<string> */
    public const EPD_TYPES = ['etrn', 'expedition_receipt'];

    /** @var list<string> */
    public const EPD_SLOT_KINDS = ['etrn', 'expedition_receipt'];

    public static function isEpdSlotKind(string $slotKind): bool
    {
        return in_array($slotKind, self::EPD_SLOT_KINDS, true);
    }

    public static function isEpdDocumentType(string $documentType): bool
    {
        return in_array($documentType, self::EPD_TYPES, true);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  iterable<OrderDocument|array<string, mixed>>  $documents
     * @param  iterable<OrderDocumentEdoAcknowledgement|array<string, mixed>>  $edoAcknowledgements
     */
    public static function isRuleFulfilled(array $rule, iterable $documents, iterable $edoAcknowledgements): bool
    {
        $slotKind = (string) ($rule['slot_kind'] ?? '');
        if (! self::isEpdSlotKind($slotKind)) {
            return false;
        }

        $documentType = $slotKind;

        return self::hasTypeFulfilled($documentType, $rule, $documents, $edoAcknowledgements);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  iterable<OrderDocument|array<string, mixed>>  $documents
     * @param  iterable<OrderDocumentEdoAcknowledgement|array<string, mixed>>  $edoAcknowledgements
     */
    public static function hasTypeFulfilled(
        string $documentType,
        array $rule,
        iterable $documents,
        iterable $edoAcknowledgements,
    ): bool {
        foreach ($documents as $document) {
            if (self::documentMatchesEpdType($document, $documentType, $rule)
                && OrderDocumentClosingFulfillment::documentFileFulfilled($document)) {
                return true;
            }
        }

        foreach ($edoAcknowledgements as $acknowledgement) {
            if (self::acknowledgementMatchesEpdType($acknowledgement, $documentType, $rule)
                && OrderDocumentClosingFulfillment::acknowledgementFulfilled($acknowledgement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     * @param  array<string, mixed>  $rule
     */
    public static function documentMatchesEpdType(
        OrderDocument|array $document,
        string $documentType,
        array $rule,
    ): bool {
        if (OrderDocumentDirection::isOutgoing($document)) {
            return false;
        }

        $type = $document instanceof OrderDocument
            ? (string) $document->type
            : (string) ($document['type'] ?? '');

        if ($type !== $documentType) {
            return false;
        }

        return self::matchesRuleContext($document, $rule);
    }

    /**
     * @param  OrderDocumentEdoAcknowledgement|array<string, mixed>  $acknowledgement
     * @param  array<string, mixed>  $rule
     */
    public static function acknowledgementMatchesEpdType(
        OrderDocumentEdoAcknowledgement|array $acknowledgement,
        string $documentType,
        array $rule,
    ): bool {
        $type = $acknowledgement instanceof OrderDocumentEdoAcknowledgement
            ? (string) $acknowledgement->document_type
            : (string) ($acknowledgement['document_type'] ?? '');

        if ($type !== $documentType) {
            return false;
        }

        return self::matchesRuleContext($acknowledgement, $rule);
    }

    /**
     * @param  OrderDocument|OrderDocumentEdoAcknowledgement|array<string, mixed>  $item
     * @param  array<string, mixed>  $rule
     */
    private static function matchesRuleContext(
        OrderDocument|OrderDocumentEdoAcknowledgement|array $item,
        array $rule,
    ): bool {
        $party = $item instanceof OrderDocument
            ? (string) data_get($item->metadata, 'party', 'internal')
            : ($item instanceof OrderDocumentEdoAcknowledgement
                ? (string) $item->party
                : (string) ($item['party'] ?? 'internal'));

        $ruleParty = (string) ($rule['party'] ?? '');
        if ($party !== $ruleParty) {
            return false;
        }

        return true;
    }
}
