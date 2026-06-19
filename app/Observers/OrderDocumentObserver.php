<?php

namespace App\Observers;

use App\Models\OrderDocument;
use App\Services\OrderClosingDocumentsNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderDocumentObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly OrderClosingDocumentsNotificationService $closingDocumentsNotificationService,
    ) {}

    public function saved(OrderDocument $document): void
    {
        if ($document->order_id === null) {
            return;
        }

        $order = $document->relationLoaded('order')
            ? $document->order
            : $document->order()->first();

        if ($order === null) {
            return;
        }

        $this->closingDocumentsNotificationService->maybeNotify($order);
    }
}
