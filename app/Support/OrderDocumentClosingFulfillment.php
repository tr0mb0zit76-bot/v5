<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;

/**
 * Закрывающие: либо УПД, либо связка счёт-фактура + акт (файл или ЭДО).
 */
final class OrderDocumentClosingFulfillment
{
    /** @var list<string> */
    public const CLOSING_TYPES = ['upd', 'invoice_factura', 'act'];

    /** @var list<string> */
    public const CLOSING_SLOT_KINDS = ['customer_closing', 'carrier_closing', 'contractor_closing'];

    public static function isClosingSlotKind(string $slotKind): bool
    {
        return in_array($slotKind, self::CLOSING_SLOT_KINDS, true);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  iterable<OrderDocument|array<string, mixed>>  $documents
     * @param  iterable<OrderDocumentEdoAcknowledgement|array<string, mixed>>  $edoAcknowledgements
     */
    public static function isRuleFulfilled(array $rule, iterable $documents, iterable $edoAcknowledgements): bool
    {
        if (! self::isClosingSlotKind((string) ($rule['slot_kind'] ?? ''))) {
            return false;
        }

        $hasUpd = self::hasTypeFulfilled('upd', $rule, $documents, $edoAcknowledgements);
        $hasAct = self::hasTypeFulfilled('act', $rule, $documents, $edoAcknowledgements);
        $hasInvoiceFactura = self::hasTypeFulfilled('invoice_factura', $rule, $documents, $edoAcknowledgements);

        return $hasUpd || ($hasAct && $hasInvoiceFactura);
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
            if (self::documentMatchesClosingType($document, $documentType, $rule)
                && self::documentFileFulfilled($document)) {
                return true;
            }
        }

        foreach ($edoAcknowledgements as $acknowledgement) {
            if (self::acknowledgementMatchesClosingType($acknowledgement, $documentType, $rule)
                && self::acknowledgementFulfilled($acknowledgement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  OrderDocument|array<string, mixed>  $document
     * @param  array<string, mixed>  $rule
     */
    public static function documentMatchesClosingType(OrderDocument|array $document, string $documentType, array $rule): bool
    {
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
    public static function acknowledgementMatchesClosingType(
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
     * @param  OrderDocument|array<string, mixed>  $document
     */
    public static function documentFileFulfilled(OrderDocument|array $document): bool
    {
        if ($document instanceof OrderDocument) {
            $status = (string) ($document->status ?? '');

            return in_array($status, ['sent', 'signed'], true);
        }

        $status = (string) ($document['status'] ?? '');

        return in_array($status, ['sent', 'signed'], true);
    }

    /**
     * @param  OrderDocumentEdoAcknowledgement|array<string, mixed>  $acknowledgement
     */
    public static function acknowledgementFulfilled(OrderDocumentEdoAcknowledgement|array $acknowledgement): bool
    {
        $received = $acknowledgement instanceof OrderDocumentEdoAcknowledgement
            ? (bool) $acknowledgement->received_via_edo
            : (bool) ($acknowledgement['received_via_edo'] ?? false);

        if (! $received) {
            return false;
        }

        $number = $acknowledgement instanceof OrderDocumentEdoAcknowledgement
            ? trim((string) ($acknowledgement->document_number ?? ''))
            : trim((string) ($acknowledgement['document_number'] ?? ''));

        return $number !== '';
    }

    /**
     * @param  OrderDocument|OrderDocumentEdoAcknowledgement|array<string, mixed>  $item
     * @param  array<string, mixed>  $rule
     */
    private static function matchesRuleContext(OrderDocument|OrderDocumentEdoAcknowledgement|array $item, array $rule): bool
    {
        $party = $item instanceof OrderDocument || $item instanceof OrderDocumentEdoAcknowledgement
            ? (string) ($item instanceof OrderDocument
                ? data_get($item->metadata, 'party', 'internal')
                : $item->party)
            : (string) ($item['party'] ?? 'internal');

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
