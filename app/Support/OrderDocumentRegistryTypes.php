<?php

namespace App\Support;

use App\Models\OrderDocument;

/**
 * Values allowed for {@see OrderDocument::$type} on registry uploads and inline order payloads.
 */
final class OrderDocumentRegistryTypes
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            'request',
            'contract',
            'contract_request',
            'waybill',
            'etrn',
            'cmr',
            'upd',
            'invoice',
            'invoice_factura',
            'act',
            'packing_list',
            'customs_declaration',
            'other',
        ];
    }
}
