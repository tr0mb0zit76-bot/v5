<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\OrderOneCDocument;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

final class OneCRealizationSyncService
{
    public function __construct(
        private readonly OneCRealizationMapper $mapper,
        private readonly OneCBpClient $client,
    ) {}

    /**
     * @return array{document: OrderOneCDocument, created: bool}
     */
    public function createForOrder(Order $order, User $user, bool $force = false): array
    {
        $this->assertReady();

        $existing = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->first();

        if ($existing !== null && $existing->status === OrderOneCDocument::STATUS_CREATED && ! $force) {
            return ['document' => $existing, 'created' => false];
        }

        $payload = $this->mapper->map($order);

        $document = $existing ?? new OrderOneCDocument([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
        ]);

        $document->fill([
            'status' => OrderOneCDocument::STATUS_PENDING,
            'amount' => $payload['amount'],
            'counterparty_inn' => $payload['counterparty']['inn'],
            'counterparty_kpp' => $payload['counterparty']['kpp'],
            'request_payload' => $payload,
            'response_payload' => null,
            'last_error' => null,
            'created_by' => $user->id,
        ]);
        $document->save();

        try {
            $result = $this->client->createRealization($payload);

            $document->fill([
                'status' => OrderOneCDocument::STATUS_CREATED,
                'external_ref' => $result['ref'],
                'external_number' => $result['number'],
                'external_date' => $result['date'],
                'response_payload' => $result['raw'],
                'last_error' => null,
            ]);
            $document->save();

            $this->maybeMarkAccountingHandoff($order, $user);

            return ['document' => $document->fresh() ?? $document, 'created' => true];
        } catch (Throwable $e) {
            $document->fill([
                'status' => OrderOneCDocument::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);
            $document->save();

            throw ValidationException::withMessages([
                'one_c' => 'Не удалось создать реализацию в 1С: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function wizardState(?Order $order, ?User $user = null): ?array
    {
        if ($order === null || ! Schema::hasTable('order_one_c_documents')) {
            return null;
        }

        $enabled = (bool) config('one_c.enabled', false);
        $canCreate = $enabled && RoleAccess::canCreateOneCRealization($user);
        $document = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->first();

        return [
            'enabled' => $enabled,
            'driver' => (string) config('one_c.driver', 'fake'),
            'can_create' => $canCreate,
            'realization' => $document?->toWizardSummary(),
        ];
    }

    private function assertReady(): void
    {
        if (! (bool) config('one_c.enabled', false)) {
            throw ValidationException::withMessages([
                'one_c' => 'Интеграция с 1С выключена (ONE_C_ENABLED).',
            ]);
        }

        if (! Schema::hasTable('order_one_c_documents')) {
            throw ValidationException::withMessages([
                'one_c' => 'Таблица связей с 1С не создана. Выполните миграции.',
            ]);
        }
    }

    private function maybeMarkAccountingHandoff(Order $order, User $user): void
    {
        if (! Schema::hasColumn('orders', 'accounting_handoff_at')) {
            return;
        }

        if ($order->accounting_handoff_at !== null) {
            return;
        }

        $order->forceFill([
            'accounting_handoff_at' => now(),
            'accounting_handoff_by' => $user->id,
        ])->save();
    }
}
