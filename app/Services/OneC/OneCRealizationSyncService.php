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
     * Перед удалением заказа: снять непроведённые реализации в 1С.
     * Проведённая реализация — ValidationException (заказ не удаляем).
     */
    public function deleteLinkedRealizationsForOrder(Order $order): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            return;
        }

        $documents = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->whereNotNull('external_ref')
            ->get();

        if ($documents->isEmpty()) {
            return;
        }

        if (! (bool) config('one_c.enabled', false)) {
            throw ValidationException::withMessages([
                'one_c' => 'У заказа есть связь с реализацией 1С, но ONE_C_ENABLED=false. Включите коннектор или снимите документ вручную.',
            ]);
        }

        foreach ($documents as $document) {
            $ref = (string) $document->external_ref;
            $this->client->deleteUnpostedRealization($ref);

            $document->fill([
                'status' => OrderOneCDocument::STATUS_DELETED_IN_1C,
                'last_error' => null,
            ]);
            $document->save();
        }
    }

    /**
     * Ручная/аварийная очистка по Ref (сироты, смоук-тесты).
     *
     * @return array{deleted: bool, number: ?string, posted: bool}
     */
    public function deleteRealizationByExternalRef(string $ref): array
    {
        $this->assertReady();

        $before = $this->client->getRealization($ref);
        if ($before === null) {
            $this->markCrmLinksDeleted($ref);

            return ['deleted' => false, 'number' => null, 'posted' => false];
        }

        $this->client->deleteUnpostedRealization($ref);
        $this->markCrmLinksDeleted($ref);

        return [
            'deleted' => true,
            'number' => $before['number'],
            'posted' => false,
        ];
    }

    private function markCrmLinksDeleted(string $ref): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            return;
        }

        OrderOneCDocument::query()
            ->where('external_ref', $ref)
            ->update([
                'status' => OrderOneCDocument::STATUS_DELETED_IN_1C,
                'last_error' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Создать или обновить реализацию по снимку заказа (CRM → 1С).
     * Проведённая в 1С — запрет. Без изменений payload — no-op.
     *
     * @return array{
     *     document: OrderOneCDocument,
     *     action: 'created'|'updated'|'unchanged',
     *     created: bool,
     *     updated: bool
     * }
     */
    public function pushForOrder(Order $order, User $user): array
    {
        $this->assertReady();

        $payload = $this->mapper->map($order);
        $fingerprint = $this->payloadFingerprint($payload);

        $existing = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->first();

        $hasLiveLink = $existing !== null
            && $existing->status === OrderOneCDocument::STATUS_CREATED
            && filled($existing->external_ref);

        if ($hasLiveLink) {
            return $this->updateExisting($order, $user, $existing, $payload, $fingerprint);
        }

        return $this->createNew($order, $user, $existing, $payload, $fingerprint);
    }

    /**
     * @deprecated use pushForOrder
     *
     * @return array{document: OrderOneCDocument, created: bool}
     */
    public function createForOrder(Order $order, User $user, bool $force = false): array
    {
        unset($force);
        $result = $this->pushForOrder($order, $user);

        return [
            'document' => $result['document'],
            'created' => $result['created'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{document: OrderOneCDocument, action: 'created', created: true, updated: false}
     */
    private function createNew(
        Order $order,
        User $user,
        ?OrderOneCDocument $existing,
        array $payload,
        string $fingerprint,
    ): array {
        $document = $existing ?? new OrderOneCDocument([
            'order_id' => $order->id,
            'document_type' => OrderOneCDocument::TYPE_REALIZATION,
        ]);

        $document->fill([
            'status' => OrderOneCDocument::STATUS_PENDING,
            'amount' => $payload['amount'],
            'counterparty_inn' => $payload['counterparty']['inn'],
            'counterparty_kpp' => $payload['counterparty']['kpp'],
            'request_payload' => $this->payloadWithFingerprint($payload, $fingerprint),
            'response_payload' => null,
            'last_error' => null,
            'created_by' => $user->id,
        ]);
        $document->save();

        try {
            $result = $this->client->createRealization($payload);

            $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];
            $raw['Posted'] = (bool) ($raw['Posted'] ?? false);

            $document->fill([
                'status' => OrderOneCDocument::STATUS_CREATED,
                'external_ref' => $result['ref'],
                'external_number' => $result['number'],
                'external_date' => $result['date'],
                'response_payload' => $raw,
                'last_error' => null,
            ]);
            $document->save();

            $this->maybeMarkAccountingHandoff($order, $user);

            return [
                'document' => $document->fresh() ?? $document,
                'action' => 'created',
                'created' => true,
                'updated' => false,
            ];
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
     * @param  array<string, mixed>  $payload
     * @return array{document: OrderOneCDocument, action: 'updated'|'unchanged', created: false, updated: bool}
     */
    private function updateExisting(
        Order $order,
        User $user,
        OrderOneCDocument $document,
        array $payload,
        string $fingerprint,
    ): array {
        $ref = (string) $document->external_ref;
        $remote = $this->client->getRealization($ref);

        if ($remote === null) {
            throw ValidationException::withMessages([
                'one_c' => 'Реализация в 1С не найдена (удалена?). Создайте заново после очистки связи.',
            ]);
        }

        $this->rememberPostedFlag($document, $remote['posted']);

        if ($remote['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Реализация в 1С проведена — изменение из CRM запрещено.',
            ]);
        }

        $previousFingerprint = $this->storedFingerprint($document);
        if ($previousFingerprint !== null && hash_equals($previousFingerprint, $fingerprint)) {
            return [
                'document' => $document,
                'action' => 'unchanged',
                'created' => false,
                'updated' => false,
            ];
        }

        try {
            $result = $this->client->updateRealization($ref, $payload);

            $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];
            $raw['Posted'] = (bool) ($result['posted'] ?? $raw['Posted'] ?? false);

            $document->fill([
                'status' => OrderOneCDocument::STATUS_CREATED,
                'amount' => $payload['amount'],
                'counterparty_inn' => $payload['counterparty']['inn'],
                'counterparty_kpp' => $payload['counterparty']['kpp'],
                'request_payload' => $this->payloadWithFingerprint($payload, $fingerprint),
                'external_number' => $result['number'] ?? $document->external_number,
                'external_date' => $result['date'] ?? $document->external_date,
                'response_payload' => $raw,
                'last_error' => null,
                'created_by' => $document->created_by ?? $user->id,
            ]);
            $document->save();

            return [
                'document' => $document->fresh() ?? $document,
                'action' => 'updated',
                'created' => false,
                'updated' => true,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $document->fill([
                'last_error' => $e->getMessage(),
            ]);
            $document->save();

            throw ValidationException::withMessages([
                'one_c' => 'Не удалось обновить реализацию в 1С: '.$e->getMessage(),
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
        $canManage = $enabled && RoleAccess::canCreateOneCRealization($user);
        $document = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', OrderOneCDocument::TYPE_REALIZATION)
            ->first();

        $ui = $this->resolveWizardAction($order, $document, $canManage);

        return [
            'enabled' => $enabled,
            'driver' => (string) config('one_c.driver', 'fake'),
            'can_create' => $canManage,
            'can_push' => $ui['can_push'],
            'action' => $ui['action'],
            'button_label' => $ui['button_label'],
            'hint' => $ui['hint'],
            'posted' => $ui['posted'],
            'stale' => $ui['stale'],
            'realization' => $document?->toWizardSummary([
                'posted' => $ui['posted'],
                'stale' => $ui['stale'],
            ]),
        ];
    }

    /**
     * @return array{
     *     action: 'create'|'update'|'current'|'blocked_posted'|'retry_failed'|'none',
     *     can_push: bool,
     *     button_label: string,
     *     hint: ?string,
     *     posted: bool,
     *     stale: bool
     * }
     */
    private function resolveWizardAction(?Order $order, ?OrderOneCDocument $document, bool $canManage): array
    {
        if (! $canManage || $order === null) {
            return [
                'action' => 'none',
                'can_push' => false,
                'button_label' => 'Создать реализацию в 1С',
                'hint' => null,
                'posted' => false,
                'stale' => false,
            ];
        }

        $hasLiveLink = $document !== null
            && $document->status === OrderOneCDocument::STATUS_CREATED
            && filled($document->external_ref);

        if (! $hasLiveLink) {
            $failed = $document?->status === OrderOneCDocument::STATUS_FAILED;

            return [
                'action' => $failed ? 'retry_failed' : 'create',
                'can_push' => true,
                'button_label' => $failed ? 'Повторить создание в 1С' : 'Создать реализацию в 1С',
                'hint' => $failed ? ($document->last_error ?: 'Предыдущая попытка не удалась.') : null,
                'posted' => false,
                'stale' => false,
            ];
        }

        $posted = (bool) data_get($document->response_payload, 'Posted', false);
        $stale = false;

        try {
            $payload = $this->mapper->map($order);
            $stale = ! hash_equals(
                $this->storedFingerprint($document) ?? '',
                $this->payloadFingerprint($payload),
            );
            if ($this->storedFingerprint($document) === null) {
                $stale = true;
            }
        } catch (Throwable) {
            $stale = true;
        }

        if ($posted) {
            return [
                'action' => 'blocked_posted',
                'can_push' => false,
                'button_label' => 'Обновить данные в 1С',
                'hint' => 'Реализация проведена в 1С — изменение из CRM запрещено.',
                'posted' => true,
                'stale' => $stale,
            ];
        }

        if (! $stale) {
            return [
                'action' => 'current',
                'can_push' => false,
                'button_label' => 'Данные в 1С актуальны',
                'hint' => null,
                'posted' => false,
                'stale' => false,
            ];
        }

        return [
            'action' => 'update',
            'can_push' => true,
            'button_label' => 'Обновить данные в 1С',
            'hint' => 'В заказе есть изменения относительно снимка в 1С.',
            'posted' => false,
            'stale' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadWithFingerprint(array $payload, string $fingerprint): array
    {
        $payload['_crm_fingerprint'] = $fingerprint;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadFingerprint(array $payload): string
    {
        $stub = is_array($payload['odata_stub'] ?? null) ? $payload['odata_stub'] : [];
        unset($stub['_crm_counterparty_match'], $stub['_crm_organization_ref']);

        $canonical = [
            'amount' => $payload['amount'] ?? null,
            'document_date' => $payload['document_date'] ?? null,
            'counterparty' => $payload['counterparty'] ?? null,
            'vat' => $payload['vat'] ?? null,
            'service_line' => [
                'content' => $payload['service_line']['content'] ?? null,
                'amount' => $payload['service_line']['amount'] ?? null,
                'vat_rate' => $payload['service_line']['vat_rate'] ?? null,
                'vat_amount' => $payload['service_line']['vat_amount'] ?? null,
                'nomenclature_ref' => $payload['service_line']['nomenclature_ref'] ?? null,
            ],
            'odata_stub' => $stub,
        ];

        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function storedFingerprint(OrderOneCDocument $document): ?string
    {
        $payload = is_array($document->request_payload) ? $document->request_payload : [];
        $stored = $payload['_crm_fingerprint'] ?? null;

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    private function rememberPostedFlag(OrderOneCDocument $document, bool $posted): void
    {
        $raw = is_array($document->response_payload) ? $document->response_payload : [];
        if ((bool) ($raw['Posted'] ?? false) === $posted) {
            return;
        }

        $raw['Posted'] = $posted;
        $document->response_payload = $raw;
        $document->save();
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
