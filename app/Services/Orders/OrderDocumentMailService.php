<?php

namespace App\Services\Orders;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use App\Services\CommercialMailService;
use App\Services\DocumentStorageService;
use App\Support\CrmFeatureCatalog;
use App\Support\RoleAccess;

class OrderDocumentMailService
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @return array{thread: MailThread, message: MailMessage}
     */
    public function sendSignedPdf(
        User $sender,
        Order $order,
        OrderDocument $orderDocument,
        array $toEmails,
        array $ccEmails = [],
        ?string $subject = null,
        ?string $body = null,
    ): array {
        abort_unless(CrmFeatureCatalog::isEnabled('order_document_mail', $sender), 404);
        abort_unless(RoleAccess::canAccessVisibilityArea($sender, 'mail'), 403);
        abort_if($orderDocument->order_id !== $order->id, 404);
        abort_if(blank($orderDocument->generated_pdf_path), 422, 'У документа нет подписанного PDF для отправки.');

        $driver = data_get($orderDocument->metadata, 'generated_pdf_storage_driver', DocumentStorageService::DRIVER_LOCAL);
        abort_unless(
            $this->documentStorage->exists((string) $orderDocument->generated_pdf_path, $driver),
            404,
            'Файл PDF не найден в хранилище.',
        );

        $filename = $this->resolveAttachmentFilename($orderDocument);
        $attachments = [[
            'path' => (string) $orderDocument->generated_pdf_path,
            'name' => $filename,
            'driver' => $driver,
            'mime_type' => 'application/pdf',
        ]];

        $subjectLine = filled($subject)
            ? trim($subject)
            : 'Документ по заказу '.($order->order_number ?: $order->id);

        $bodyText = filled($body)
            ? trim($body)
            : "Добрый день!\n\nНаправляем документ по заказу {$order->order_number}.\n\nС уважением.";

        return $this->commercialMail->sendOutbound(
            subject: $subjectLine,
            bodyText: $bodyText,
            toEmails: $toEmails,
            sender: $sender,
            ccEmails: $ccEmails,
            attachments: $attachments,
            orderId: $order->id,
            contractorId: $order->customer_id,
        );
    }

    private function resolveAttachmentFilename(OrderDocument $orderDocument): string
    {
        $original = (string) ($orderDocument->original_name ?: 'document.pdf');
        $basename = pathinfo($original, PATHINFO_FILENAME);

        if (str_ends_with(strtolower($original), '.pdf')) {
            return $original;
        }

        return $basename.'.pdf';
    }
}
