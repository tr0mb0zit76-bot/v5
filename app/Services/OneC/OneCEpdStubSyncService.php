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

/**
 * Push болванок ЭПД (ЭТрН / экспедиторская расписка) в 1С. Sibling of OneCRealizationSyncService.
 * Без accounting_handoff.
 */
final class OneCEpdStubSyncService
{
    public function __construct(
        private readonly OneCEpdStubMapper $mapper,
        private readonly OneCBpClient $client,
    ) {}

    public function deleteLinkedEpdStubsForOrder(Order $order): void
    {
        if (! Schema::hasTable('order_one_c_documents')) {
            return;
        }

        $documents = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->whereIn('document_type', OrderOneCDocument::EPD_TYPES)
            ->whereNotNull('external_ref')
            ->get();

        if ($documents->isEmpty()) {
            return;
        }

        if (! (bool) config('one_c.enabled', false)) {
            throw ValidationException::withMessages([
                'one_c' => 'У заказа есть связь с ЭПД в 1С, но ONE_C_ENABLED=false. Включите коннектор или снимите документ вручную.',
            ]);
        }

        foreach ($documents as $document) {
            $ref = (string) $document->external_ref;
            $type = (string) $document->document_type;
            $baseUrl = $this->storedBaseUrl($document);
            $this->client->deleteUnpostedEpdStub($type, $ref, $baseUrl);

            $document->fill([
                'status' => OrderOneCDocument::STATUS_DELETED_IN_1C,
                'last_error' => null,
            ]);
            $document->save();
        }
    }

    /**
     * @return array{
     *     document: OrderOneCDocument,
     *     action: 'created'|'updated'|'unchanged',
     *     created: bool,
     *     updated: bool
     * }
     */
    public function pushForOrder(Order $order, User $user, string $documentType): array
    {
        $this->assertReady();
        $this->assertDocumentType($documentType);

        $payload = $this->mapper->map($order, $documentType);
        $fingerprint = $this->payloadFingerprint($payload);

        $existing = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', $documentType)
            ->first();

        $hasLiveLink = $existing !== null
            && $existing->status === OrderOneCDocument::STATUS_CREATED
            && filled($existing->external_ref);

        if ($hasLiveLink && $this->publicationMismatch($existing, $payload)) {
            $oldRef = (string) $existing->external_ref;
            $oldBase = $this->storedBaseUrl($existing);
            try {
                $this->client->deleteUnpostedEpdStub($documentType, $oldRef, $oldBase);
            } catch (ValidationException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw ValidationException::withMessages([
                    'one_c' => 'Не удалось снять ЭПД в прежней ИБ перед переносом: '.$e->getMessage(),
                ]);
            }

            $existing->fill([
                'status' => OrderOneCDocument::STATUS_DELETED_IN_1C,
                'external_ref' => null,
                'external_number' => null,
                'external_date' => null,
                'last_error' => null,
            ]);
            $existing->save();

            return $this->createNew($order, $user, $existing, $payload, $fingerprint, $documentType);
        }

        if ($hasLiveLink) {
            return $this->updateExisting($order, $user, $existing, $payload, $fingerprint, $documentType);
        }

        return $this->createNew($order, $user, $existing, $payload, $fingerprint, $documentType);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function wizardState(?Order $order, ?User $user, string $documentType): ?array
    {
        if ($order === null || ! Schema::hasTable('order_one_c_documents')) {
            return null;
        }

        $this->assertDocumentType($documentType);

        $enabled = (bool) config('one_c.enabled', false);
        $canManage = $enabled && RoleAccess::canCreateOneCRealization($user);
        $document = OrderOneCDocument::query()
            ->where('order_id', $order->id)
            ->where('document_type', $documentType)
            ->first();

        $ui = $this->resolveWizardAction($order, $document, $canManage, $documentType);

        return [
            'enabled' => $enabled,
            'driver' => (string) config('one_c.driver', 'fake'),
            'document_type' => $documentType,
            'can_create' => $canManage,
            'can_push' => $ui['can_push'],
            'action' => $ui['action'],
            'button_label' => $ui['button_label'],
            'hint' => $ui['hint'],
            'posted' => $ui['posted'],
            'stale' => $ui['stale'],
            'document' => $document?->toWizardSummary([
                'posted' => $ui['posted'],
                'stale' => $ui['stale'],
            ]),
        ];
    }

    /**
     * @return array{etrn: array<string, mixed>|null, expedition_receipt: array<string, mixed>|null}
     */
    public function wizardStates(?Order $order, ?User $user = null): array
    {
        return [
            OrderOneCDocument::TYPE_ETRN => $this->wizardState($order, $user, OrderOneCDocument::TYPE_ETRN),
            OrderOneCDocument::TYPE_EXPEDITION_RECEIPT => $this->wizardState(
                $order,
                $user,
                OrderOneCDocument::TYPE_EXPEDITION_RECEIPT,
            ),
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
        string $documentType,
    ): array {
        $document = $existing ?? new OrderOneCDocument([
            'order_id' => $order->id,
            'document_type' => $documentType,
        ]);

        $document->fill([
            'status' => OrderOneCDocument::STATUS_PENDING,
            'amount' => null,
            'counterparty_inn' => $payload['counterparty']['inn'] ?? null,
            'counterparty_kpp' => $payload['counterparty']['kpp'] ?? null,
            'request_payload' => $this->payloadWithFingerprint($payload, $fingerprint),
            'response_payload' => null,
            'last_error' => null,
            'created_by' => $user->id,
        ]);
        $document->save();

        try {
            $result = $this->client->createEpdStub($documentType, $payload);

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
                'one_c' => 'Не удалось создать ЭПД в 1С: '.$e->getMessage(),
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
        string $documentType,
    ): array {
        $ref = (string) $document->external_ref;
        $remote = $this->client->getEpdStub(
            $documentType,
            $ref,
            $this->storedBaseUrl($document) ?? $this->payloadBaseUrl($payload),
        );

        if ($remote === null) {
            throw ValidationException::withMessages([
                'one_c' => 'Документ ЭПД в 1С не найден (удалён?). Создайте заново после очистки связи.',
            ]);
        }

        $this->rememberPostedFlag($document, $remote['posted']);

        if ($remote['posted']) {
            throw ValidationException::withMessages([
                'one_c' => $this->postedBlockMessage($documentType),
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
            $result = $this->client->updateEpdStub($documentType, $ref, $payload);

            $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];
            $raw['Posted'] = (bool) ($result['posted'] ?? $raw['Posted'] ?? false);

            $document->fill([
                'status' => OrderOneCDocument::STATUS_CREATED,
                'counterparty_inn' => $payload['counterparty']['inn'] ?? null,
                'counterparty_kpp' => $payload['counterparty']['kpp'] ?? null,
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
                'one_c' => 'Не удалось обновить ЭПД в 1С: '.$e->getMessage(),
            ]);
        }
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
    private function resolveWizardAction(
        ?Order $order,
        ?OrderOneCDocument $document,
        bool $canManage,
        string $documentType,
    ): array {
        $labels = $this->labelsFor($documentType);

        if (! $canManage || $order === null) {
            return [
                'action' => 'none',
                'can_push' => false,
                'button_label' => $labels['create'],
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
                'button_label' => $failed ? $labels['retry'] : $labels['create'],
                'hint' => $failed ? ($document->last_error ?: 'Предыдущая попытка не удалась.') : null,
                'posted' => false,
                'stale' => false,
            ];
        }

        $posted = (bool) data_get($document->response_payload, 'Posted', false);
        $stale = false;

        try {
            $payload = $this->mapper->map($order, $documentType);
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
                'button_label' => $labels['update'],
                'hint' => $this->postedBlockMessage($documentType),
                'posted' => true,
                'stale' => $stale,
            ];
        }

        if (! $stale) {
            return [
                'action' => 'current',
                'can_push' => false,
                'button_label' => $labels['current'],
                'hint' => null,
                'posted' => false,
                'stale' => false,
            ];
        }

        return [
            'action' => 'update',
            'can_push' => true,
            'button_label' => $labels['update'],
            'hint' => 'В заказе есть изменения относительно снимка в 1С.',
            'posted' => false,
            'stale' => true,
        ];
    }

    /**
     * @return array{create: string, retry: string, update: string, current: string}
     */
    private function labelsFor(string $documentType): array
    {
        if ($documentType === OrderOneCDocument::TYPE_EXPEDITION_RECEIPT) {
            return [
                'create' => 'Создать экспедиторскую расписку в 1С',
                'retry' => 'Повторить создание расписки в 1С',
                'update' => 'Обновить расписку в 1С',
                'current' => 'Расписка в 1С актуальна',
            ];
        }

        return [
            'create' => 'Создать ЭТрН в 1С',
            'retry' => 'Повторить создание ЭТрН в 1С',
            'update' => 'Обновить ЭТрН в 1С',
            'current' => 'ЭТрН в 1С актуальна',
        ];
    }

    private function postedBlockMessage(string $documentType): string
    {
        $label = $documentType === OrderOneCDocument::TYPE_EXPEDITION_RECEIPT
            ? 'Экспедиторская расписка'
            : 'ЭТрН';

        return $label.' проведена в 1С — изменение из CRM запрещено.';
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
            'document_type' => $payload['document_type'] ?? null,
            'document_date' => $payload['document_date'] ?? null,
            'parties' => $payload['parties'] ?? null,
            'counterparty' => $payload['counterparty'] ?? null,
            'route_points' => $payload['route_points'] ?? null,
            'cargo' => $payload['cargo'] ?? null,
            'driver' => $payload['driver'] ?? null,
            'vehicle' => $payload['vehicle'] ?? null,
            'organization_ref' => $payload['organization_ref'] ?? null,
            'publication_code' => $payload['publication_code'] ?? null,
            'base_url' => $payload['base_url'] ?? null,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publicationMismatch(OrderOneCDocument $document, array $payload): bool
    {
        $stored = is_array($document->request_payload) ? $document->request_payload : [];
        $storedCode = (string) ($stored['publication_code'] ?? '');
        $newCode = (string) ($payload['publication_code'] ?? '');
        if ($storedCode !== '' && $newCode !== '' && $storedCode !== $newCode) {
            return true;
        }

        $storedBase = rtrim((string) ($stored['base_url'] ?? ''), '/');
        $newBase = rtrim((string) ($payload['base_url'] ?? ''), '/');
        if ($storedBase !== '' && $newBase !== '' && $storedBase !== $newBase) {
            return true;
        }

        $storedOrg = (string) ($stored['organization_ref'] ?? '');
        $newOrg = (string) ($payload['organization_ref'] ?? '');

        return $storedOrg !== '' && $newOrg !== '' && $storedOrg !== $newOrg;
    }

    private function storedBaseUrl(OrderOneCDocument $document): ?string
    {
        $payload = is_array($document->request_payload) ? $document->request_payload : [];

        return $this->payloadBaseUrl($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadBaseUrl(array $payload): ?string
    {
        $base = is_string($payload['base_url'] ?? null) ? trim((string) $payload['base_url']) : '';

        return $base !== '' ? rtrim($base, '/') : null;
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

    private function assertDocumentType(string $documentType): void
    {
        if (! in_array($documentType, OrderOneCDocument::EPD_TYPES, true)) {
            throw ValidationException::withMessages([
                'one_c' => 'Неизвестный тип ЭПД: '.$documentType,
            ]);
        }
    }
}
