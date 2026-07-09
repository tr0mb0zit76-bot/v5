<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Models\User;
use App\Support\OrderDocumentClosingFulfillment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class OrderDocumentEdoAcknowledgementService
{
    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
    ) {}

    /**
     * @return Collection<int, OrderDocumentEdoAcknowledgement>
     */
    public function acknowledgementsForOrder(Order $order): Collection
    {
        if (! Schema::hasTable('order_document_edo_acknowledgements')) {
            return collect();
        }

        if ($order->relationLoaded('edoAcknowledgements')) {
            return $order->edoAcknowledgements;
        }

        return OrderDocumentEdoAcknowledgement::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForOrder(Order $order): array
    {
        return $this->acknowledgementsForOrder($order)
            ->map(fn (OrderDocumentEdoAcknowledgement $row): array => $this->serializeRow($row))
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     party: string,
     *     document_type: string,
     *     slot_key?: string|null,
     *     contractor_id?: int|null,
     *     received_via_edo: bool,
     *     document_number?: string|null,
     *     document_date?: string|null,
     * }  $payload
     */
    public function upsertForOrder(Order $order, array $payload, User $actor): OrderDocumentEdoAcknowledgement
    {
        if (! Schema::hasTable('order_document_edo_acknowledgements')) {
            throw new \RuntimeException('Таблица ЭДО-подтверждений недоступна.');
        }

        if (! in_array($payload['document_type'], OrderDocumentClosingFulfillment::CLOSING_TYPES, true)) {
            throw new \InvalidArgumentException('ЭДО доступно только для закрывающих документов.');
        }

        $slotKey = trim((string) ($payload['slot_key'] ?? ''));
        $contractorId = isset($payload['contractor_id']) && (int) $payload['contractor_id'] > 0
            ? (int) $payload['contractor_id']
            : 0;
        $received = (bool) ($payload['received_via_edo'] ?? false);
        $number = trim((string) ($payload['document_number'] ?? ''));
        $documentDate = filled($payload['document_date'] ?? null)
            ? (string) $payload['document_date']
            : null;

        if ($received && $number === '') {
            throw new \InvalidArgumentException('Укажите номер документа для отметки ЭДО.');
        }

        $acknowledgement = OrderDocumentEdoAcknowledgement::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'party' => strtolower(trim($payload['party'])),
                'document_type' => $payload['document_type'],
                'slot_key' => $slotKey,
                'contractor_id' => $contractorId,
            ],
            [
                'received_via_edo' => $received,
                'document_number' => $received ? $number : null,
                'document_date' => $received ? $documentDate : null,
                'confirmed_by' => $received ? $actor->id : null,
                'confirmed_at' => $received ? now() : null,
            ],
        );

        if (! $received) {
            $acknowledgement->fill([
                'document_number' => null,
                'document_date' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
            ])->save();
        }

        $this->orderCompensationService->resyncPaymentSchedulesForOrder($order->fresh());

        return $acknowledgement->fresh();
    }

    /**
     * @return array<string, bool>
     */
    public function closingColumnEdoFlags(Order $order): array
    {
        $flags = [];
        $map = [
            'customer_upd' => ['party' => 'customer', 'type' => 'upd'],
            'customer_act' => ['party' => 'customer', 'type' => 'act'],
            'customer_invoice_factura' => ['party' => 'customer', 'type' => 'invoice_factura'],
            'carrier_upd' => ['party' => 'carrier', 'type' => 'upd'],
            'carrier_act' => ['party' => 'carrier', 'type' => 'act'],
            'carrier_invoice_factura' => ['party' => 'carrier', 'type' => 'invoice_factura'],
        ];

        $documents = $order->relationLoaded('documents') ? $order->documents : $order->documents()->get();
        $acknowledgements = $this->acknowledgementsForOrder($order);

        foreach ($map as $column => $config) {
            $hasFile = $documents->contains(function ($document) use ($config): bool {
                $meta = (array) ($document->metadata ?? []);

                return $document->type === $config['type']
                    && ($meta['party'] ?? 'internal') === $config['party']
                    && filled($document->file_path);
            });

            $hasEdo = $acknowledgements->contains(function (OrderDocumentEdoAcknowledgement $row) use ($config): bool {
                return $row->party === $config['party']
                    && $row->document_type === $config['type']
                    && (bool) $row->received_via_edo
                    && filled($row->document_number);
            });

            $flags[$column] = ! $hasFile && $hasEdo;
        }

        return $flags;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(OrderDocumentEdoAcknowledgement $row): array
    {
        return [
            'id' => $row->id,
            'party' => $row->party,
            'document_type' => $row->document_type,
            'slot_key' => $row->slot_key,
            'contractor_id' => $row->contractor_id > 0 ? $row->contractor_id : null,
            'received_via_edo' => (bool) $row->received_via_edo,
            'document_number' => $row->document_number,
            'document_date' => optional($row->document_date)?->toDateString(),
            'confirmed_at' => optional($row->confirmed_at)?->toIso8601String(),
        ];
    }
}
