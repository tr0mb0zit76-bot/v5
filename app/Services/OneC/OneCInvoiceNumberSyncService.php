<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\PaymentSchedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Подтягивает номер счёта покупателя из 1С (реализация → СчетНаОплату) в CRM.
 */
final class OneCInvoiceNumberSyncService
{
    private const EMPTY_GUID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private readonly OneCBpClient $oneC,
    ) {}

    /**
     * @return array{
     *     checked: int,
     *     updated: int,
     *     skipped_no_invoice: int,
     *     skipped_unchanged: int,
     *     errors: int
     * }
     */
    public function sync(?int $limit = null): array
    {
        $stats = [
            'checked' => 0,
            'updated' => 0,
            'skipped_no_invoice' => 0,
            'skipped_unchanged' => 0,
            'errors' => 0,
        ];

        if (! (bool) config('one_c.enabled')) {
            return $stats;
        }

        $query = OrderOneCDocument::query()
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->where('status', OrderOneCDocument::STATUS_CREATED)
            ->whereNotNull('external_ref')
            ->where('external_ref', '!=', '')
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        /** @var OrderOneCDocument $document */
        foreach ($query->cursor() as $document) {
            $stats['checked']++;

            try {
                $result = $this->syncDocument($document);
                $stats[$result]++;
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('one_c.invoice_sync_failed', [
                    'order_one_c_document_id' => $document->id,
                    'order_id' => $document->order_id,
                    'external_ref' => $document->external_ref,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return 'updated'|'skipped_no_invoice'|'skipped_unchanged'
     */
    private function syncDocument(OrderOneCDocument $document): string
    {
        $realization = $this->oneC->getRealization((string) $document->external_ref);
        if ($realization === null) {
            return 'skipped_no_invoice';
        }

        $invoiceRef = trim((string) ($realization['raw']['СчетНаОплатуПокупателю_Key'] ?? ''));
        if ($invoiceRef === '' || $invoiceRef === self::EMPTY_GUID) {
            return 'skipped_no_invoice';
        }

        $invoice = $this->oneC->getBuyerInvoice($invoiceRef);
        $invoiceNumber = trim((string) ($invoice['number'] ?? ''));
        if ($invoice === null || $invoiceNumber === '') {
            return 'skipped_no_invoice';
        }

        $payload = is_array($document->response_payload) ? $document->response_payload : [];
        $previousNumber = trim((string) ($payload['invoice_number'] ?? ''));
        $previousRef = trim((string) ($payload['invoice_ref'] ?? ''));

        $orderChanged = $this->applyToOrder((int) $document->order_id, $invoiceNumber);
        $schedulesChanged = $this->applyToCustomerSchedules((int) $document->order_id, $invoiceNumber);

        $metaChanged = $previousNumber !== $invoiceNumber || $previousRef !== $invoiceRef;
        if ($metaChanged) {
            $payload['invoice_ref'] = $invoiceRef;
            $payload['invoice_number'] = $invoiceNumber;
            $payload['invoice_synced_at'] = now()->toIso8601String();
            $document->response_payload = $payload;
            $document->save();
        }

        if (! $orderChanged && ! $schedulesChanged && ! $metaChanged) {
            return 'skipped_unchanged';
        }

        return 'updated';
    }

    private function applyToOrder(int $orderId, string $invoiceNumber): bool
    {
        if ($orderId <= 0 || ! Schema::hasColumn('orders', 'invoice_number')) {
            return false;
        }

        $order = Order::query()->find($orderId);
        if ($order === null) {
            return false;
        }

        $current = trim((string) ($order->invoice_number ?? ''));
        if ($current === $invoiceNumber) {
            return false;
        }

        // Не затираем чужой ручной номер, если он уже другой и не из прошлого sync —
        // но источник истины контура 1С: если пусто или совпадает со старым — пишем.
        if ($current !== '' && $current !== $invoiceNumber) {
            // Ручной/иной номер: не трогаем заказ, график всё равно можем дозаполнить пустые.
            return false;
        }

        $order->invoice_number = $invoiceNumber;
        $order->save();

        return true;
    }

    private function applyToCustomerSchedules(int $orderId, string $invoiceNumber): bool
    {
        if ($orderId <= 0 || ! Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return false;
        }

        $changed = false;
        $schedules = PaymentSchedule::query()
            ->where('order_id', $orderId)
            ->where('party', 'customer')
            ->get();

        foreach ($schedules as $schedule) {
            $current = trim((string) ($schedule->invoice_number ?? ''));
            if ($current === $invoiceNumber) {
                continue;
            }
            if ($current !== '') {
                continue;
            }

            $schedule->invoice_number = $invoiceNumber;
            $schedule->save();
            $changed = true;
        }

        return $changed;
    }
}
