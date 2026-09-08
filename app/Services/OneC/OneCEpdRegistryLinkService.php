<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\OneCEpdRegistryEntry;
use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Связка строк реестра ЭПД с заказом CRM (+ зеркало в order_one_c_documents для ЭТрН/ЭР).
 */
final class OneCEpdRegistryLinkService
{
    public function __construct(
        private readonly OneCEpdDocumentTypeResolver $typeResolver,
    ) {}

    public function link(OneCEpdRegistryEntry $entry, Order $order, User $user): OneCEpdRegistryEntry
    {
        if (! OrderViewAuthorization::userCanMutateOrder($user, $order)) {
            throw ValidationException::withMessages([
                'order_id' => 'Нет доступа к выбранному заказу.',
            ]);
        }

        if ($entry->order_id !== null && (int) $entry->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'order_id' => 'Документ уже связан с другим заказом. Сначала отвяжите.',
            ]);
        }

        return $this->persistLink($entry, $order, $user);
    }

    /**
     * Авто-связь из sync ЭТрН (без RBAC). Не перезаписывает чужую связь.
     */
    public function linkAutomatically(OneCEpdRegistryEntry $entry, Order $order, ?User $actor = null): OneCEpdRegistryEntry
    {
        if ($entry->order_id !== null) {
            return $entry->fresh(['order:id,order_number']) ?? $entry;
        }

        return $this->persistLink($entry, $order, $actor);
    }

    private function persistLink(OneCEpdRegistryEntry $entry, Order $order, ?User $user): OneCEpdRegistryEntry
    {
        return DB::transaction(function () use ($entry, $order, $user): OneCEpdRegistryEntry {
            $entry->forceFill([
                'order_id' => $order->id,
                'linked_by' => $user?->id,
                'linked_at' => now(),
            ])->save();

            $this->mirrorToOrderOneCDocument($entry, $order, $user);

            return $entry->fresh(['order:id,order_number']) ?? $entry;
        });
    }

    public function unlink(OneCEpdRegistryEntry $entry, User $user): OneCEpdRegistryEntry
    {
        if ($entry->order_id === null) {
            return $entry;
        }

        $order = Order::query()->find((int) $entry->order_id);
        if ($order !== null && ! OrderViewAuthorization::userCanMutateOrder($user, $order)) {
            throw ValidationException::withMessages([
                'order_id' => 'Нет доступа к связанному заказу.',
            ]);
        }

        return DB::transaction(function () use ($entry): OneCEpdRegistryEntry {
            $orderId = (int) $entry->order_id;
            $documentRef = (string) $entry->document_ref;
            $documentType = (string) $entry->document_type;

            $entry->forceFill([
                'order_id' => null,
                'linked_by' => null,
                'linked_at' => null,
            ])->save();

            $this->clearRegistryMirror($orderId, $documentRef, $documentType);

            return $entry->fresh(['order:id,order_number']) ?? $entry;
        });
    }

    private function mirrorToOrderOneCDocument(OneCEpdRegistryEntry $entry, Order $order, ?User $user): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            return;
        }

        if (! in_array((string) $entry->document_type, $this->typeResolver->linkableOneCDocumentTypes(), true)) {
            return;
        }

        $mappedType = match ((string) $entry->document_type) {
            OneCEpdRegistryEntry::TYPE_ETRN => OrderOneCDocument::TYPE_ETRN,
            OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT => OrderOneCDocument::TYPE_EXPEDITION_RECEIPT,
            default => null,
        };
        if ($mappedType === null) {
            return;
        }

        $existing = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', $mappedType)
            ->where(function ($q) use ($entry): void {
                $q->where('external_ref', (string) $entry->document_ref)
                    ->orWhereNull('external_ref')
                    ->orWhere('external_ref', '');
            })
            ->orderByDesc('id')
            ->first();

        $payload = [
            'source' => 'epd_registry',
            'registry_entry_id' => (int) $entry->id,
            'publication_code' => (string) $entry->publication_code,
            'document_ref_type' => $entry->document_ref_type,
            'current_step' => $entry->current_step,
        ];

        if ($existing !== null) {
            $response = is_array($existing->response_payload) ? $existing->response_payload : [];
            $existing->forceFill([
                'status' => OrderOneCDocument::STATUS_CREATED,
                'external_ref' => (string) $entry->document_ref,
                'external_number' => $entry->epd_number ?: $entry->ib_number,
                'external_date' => $entry->epd_date,
                'counterparty_inn' => $entry->shipper_inn,
                'request_payload' => array_merge(
                    is_array($existing->request_payload) ? $existing->request_payload : [],
                    $payload,
                ),
                'response_payload' => array_merge($response, [
                    'registry_link' => true,
                    'Posted' => (bool) $entry->posted,
                ]),
                'last_error' => null,
            ])->save();

            return;
        }

        OrderOneCDocument::query()->create([
            'order_id' => $order->id,
            'document_type' => $mappedType,
            'status' => OrderOneCDocument::STATUS_CREATED,
            'external_ref' => (string) $entry->document_ref,
            'external_number' => $entry->epd_number ?: $entry->ib_number,
            'external_date' => $entry->epd_date,
            'counterparty_inn' => $entry->shipper_inn,
            'request_payload' => $payload,
            'response_payload' => [
                'registry_link' => true,
                'Posted' => (bool) $entry->posted,
            ],
            'created_by' => $user?->id,
        ]);
    }

    private function clearRegistryMirror(int $orderId, string $documentRef, string $documentType): void
    {
        if (! Schema::hasTable('order_one_c_documents') || $orderId <= 0 || $documentRef === '') {
            return;
        }

        $mappedType = match ($documentType) {
            OneCEpdRegistryEntry::TYPE_ETRN => OrderOneCDocument::TYPE_ETRN,
            OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT => OrderOneCDocument::TYPE_EXPEDITION_RECEIPT,
            default => null,
        };
        if ($mappedType === null) {
            return;
        }

        $docs = OrderOneCDocument::query()
            ->where('order_id', $orderId)
            ->where('document_type', $mappedType)
            ->where('external_ref', $documentRef)
            ->get();

        foreach ($docs as $doc) {
            $request = is_array($doc->request_payload) ? $doc->request_payload : [];
            if (($request['source'] ?? null) !== 'epd_registry') {
                continue;
            }
            $doc->delete();
        }
    }
}
