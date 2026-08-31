<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;

/**
 * Слоты заявки чек-листа: файл sent/signed или отметка ЭДО (как закрывающие).
 */
final class OrderDocumentRequestEdoFulfillment
{
    /** @var list<string> */
    public const REQUEST_TYPES = ['request', 'contract_request'];

    /** @var list<string> */
    public const REQUEST_SLOT_KINDS = ['customer_request', 'carrier_request', 'contractor_request'];

    public static function isRequestSlotKind(string $slotKind): bool
    {
        return in_array($slotKind, self::REQUEST_SLOT_KINDS, true);
    }

    public static function isRequestDocumentType(string $documentType): bool
    {
        return in_array($documentType, self::REQUEST_TYPES, true);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  iterable<OrderDocument|array<string, mixed>>  $documents
     * @param  iterable<OrderDocumentEdoAcknowledgement|array<string, mixed>>  $edoAcknowledgements
     */
    public static function isRuleFulfilled(array $rule, iterable $documents, iterable $edoAcknowledgements): bool
    {
        if (! self::isRequestSlotKind((string) ($rule['slot_kind'] ?? ''))) {
            return false;
        }

        foreach (self::REQUEST_TYPES as $documentType) {
            if (self::hasTypeFulfilled($documentType, $rule, $documents, $edoAcknowledgements)) {
                return true;
            }
        }

        return false;
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
            if (self::documentMatchesRequestType($document, $documentType, $rule)
                && OrderDocumentClosingFulfillment::documentFileFulfilled($document)) {
                return true;
            }
        }

        foreach ($edoAcknowledgements as $acknowledgement) {
            if (self::acknowledgementMatchesRequestType($acknowledgement, $documentType, $rule)
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
    public static function documentMatchesRequestType(
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
    public static function acknowledgementMatchesRequestType(
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

        if ($party !== (string) ($rule['party'] ?? '')) {
            return false;
        }

        $ruleContractorId = isset($rule['contractor_id']) && (int) $rule['contractor_id'] > 0
            ? (int) $rule['contractor_id']
            : 0;
        $itemContractorId = self::resolveContractorId($item);

        if ($ruleContractorId > 0 && $itemContractorId !== $ruleContractorId) {
            return false;
        }

        $ruleSlotKey = filled($rule['slot_key'] ?? null) ? (string) $rule['slot_key'] : '';
        $itemSlotKey = self::resolveSlotKey($item);

        if ($ruleSlotKey !== '' && $itemSlotKey !== '' && $itemSlotKey !== $ruleSlotKey) {
            return false;
        }

        return true;
    }

    /**
     * @param  OrderDocument|OrderDocumentEdoAcknowledgement|array<string, mixed>  $item
     */
    private static function resolveContractorId(OrderDocument|OrderDocumentEdoAcknowledgement|array $item): int
    {
        if ($item instanceof OrderDocumentEdoAcknowledgement) {
            return (int) ($item->contractor_id ?? 0);
        }

        if ($item instanceof OrderDocument) {
            $meta = (array) ($item->metadata ?? []);

            return (int) ($meta['carrier_contractor_id'] ?? $meta['contractor_id'] ?? 0);
        }

        return (int) ($item['contractor_id'] ?? $item['carrier_contractor_id'] ?? 0);
    }

    /**
     * @param  OrderDocument|OrderDocumentEdoAcknowledgement|array<string, mixed>  $item
     */
    private static function resolveSlotKey(OrderDocument|OrderDocumentEdoAcknowledgement|array $item): string
    {
        if ($item instanceof OrderDocumentEdoAcknowledgement) {
            return (string) ($item->slot_key ?? '');
        }

        if ($item instanceof OrderDocument) {
            $meta = (array) ($item->metadata ?? []);

            return (string) ($meta['requirement_slot_key'] ?? '');
        }

        return (string) ($item['slot_key'] ?? $item['requirement_slot_key'] ?? '');
    }
}
