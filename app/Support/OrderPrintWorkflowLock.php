<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderDocument;
use Illuminate\Support\Facades\Schema;

/**
 * Блокировка редактирования заказа владельцем, когда все печатные заявки по шаблону доведены до финального PDF.
 */
final class OrderPrintWorkflowLock
{
    public static function allPrintWorkflowDocumentsFinalized(Order $order): bool
    {
        if (! Schema::hasTable('order_documents')) {
            return false;
        }

        $documents = $order->relationLoaded('documents')
            ? $order->documents
            : OrderDocument::query()->where('order_id', $order->id)->get();

        $printDocs = $documents->filter(fn (OrderDocument $document): bool => self::isPrintWorkflowDocument($document));

        if ($printDocs->isEmpty()) {
            return false;
        }

        foreach ($printDocs as $document) {
            $workflowStatus = Schema::hasColumn('order_documents', 'workflow_status')
                ? $document->workflow_status
                : null;

            if ($workflowStatus !== OrderDocumentWorkflowStatus::FINALIZED) {
                return false;
            }
        }

        return true;
    }

    private static function isPrintWorkflowDocument(OrderDocument $document): bool
    {
        return (Schema::hasColumn('order_documents', 'source') && $document->source === 'print_template')
            || (data_get($document->metadata, 'flow') === 'print_template_workflow');
    }
}
