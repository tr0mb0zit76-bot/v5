<?php

declare(strict_types=1);

namespace App\Services\OneC;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Транспорт к 1С БП. driver=fake — без сети; http — OData POST + поиск контрагента по ИНН/КПП.
 */
final class OneCBpClient
{
    /**
     * @param  array<string, mixed>  $payload  результат OneCRealizationMapper::map()
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>}
     */
    public function createRealization(array $payload): array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->createRealizationFake($payload),
            'http' => $this->createRealizationHttp($payload),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * Обновить непроведённую реализацию (CRM → 1С). Проведённую — ValidationException.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>, posted: bool}
     */
    public function updateRealization(string $ref, array $payload): array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->updateRealizationFake($ref, $payload),
            'http' => $this->updateRealizationHttp($ref, $payload),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * Создать болванку ЭПД (ЭТрН / экспедиторская расписка).
     *
     * @param  array<string, mixed>  $payload  результат OneCEpdStubMapper::map()
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>}
     */
    public function createEpdStub(string $documentType, array $payload): array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->createEpdStubFake($payload),
            'http' => $this->createEpdStubHttp($documentType, $payload),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>, posted: bool}
     */
    public function updateEpdStub(string $documentType, string $ref, array $payload): array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->updateEpdStubFake($ref, $payload),
            'http' => $this->updateEpdStubHttp($documentType, $ref, $payload),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    public function getEpdStub(string $documentType, string $ref, ?string $baseUrl = null): ?array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->getEpdStubFake($ref),
            'http' => $this->getEpdStubHttp($documentType, $ref, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    public function deleteUnpostedEpdStub(string $documentType, string $ref, ?string $baseUrl = null): void
    {
        $driver = (string) config('one_c.driver', 'fake');

        match ($driver) {
            'fake' => $this->deleteUnpostedEpdStubFake($ref),
            'http' => $this->markUnpostedEpdStubDeletedHttp($documentType, $ref, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>}
     */
    private function createEpdStubFake(array $payload): array
    {
        $ref = (string) Str::uuid();

        return [
            'ref' => $ref,
            'number' => 'EPD-'.substr($ref, 0, 8),
            'date' => (string) ($payload['document_date'] ?? now()->toDateString()),
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Posted' => false,
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>, posted: bool}
     */
    private function updateEpdStubFake(string $ref, array $payload): array
    {
        $current = $this->getEpdStubFake($ref);
        if ($current === null) {
            throw new RuntimeException('1С: документ ЭПД не найден для обновления.');
        }

        if ($current['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Документ ЭПД в 1С проведён — изменение из CRM запрещено.',
            ]);
        }

        return [
            'ref' => $ref,
            'number' => $current['number'],
            'date' => (string) ($payload['document_date'] ?? now()->toDateString()),
            'posted' => false,
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Posted' => false,
                'Number' => $current['number'],
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    private function getEpdStubFake(string $ref): ?array
    {
        if ($ref === '' || $ref === 'missing') {
            return null;
        }

        $posted = $ref === '22222222-2222-2222-2222-222222222222';

        return [
            'ref' => $ref,
            'number' => 'EPD-'.substr($ref, 0, 8),
            'posted' => $posted,
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Posted' => $posted,
                'Number' => 'EPD-'.substr($ref, 0, 8),
            ],
        ];
    }

    private function deleteUnpostedEpdStubFake(string $ref): void
    {
        $doc = $this->getEpdStubFake($ref);
        if ($doc === null) {
            return;
        }

        if ($doc['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Документ ЭПД в 1С проведён — удаление запрещено. Сначала разберите документ в 1С.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>}
     */
    private function createEpdStubHttp(string $documentType, array $payload): array
    {
        $body = $this->epdWriteBody($payload);
        $base = $this->resolveBaseUrl($payload);
        $path = $this->epdODataPath($documentType);
        $response = $this->http()->post($base.$path, $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С отказала в создании ЭПД: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $ref = (string) ($json['Ref_Key'] ?? $json['Ref'] ?? '');
        if ($ref === '') {
            throw new RuntimeException('1С не вернула Ref_Key документа ЭПД.');
        }

        return [
            'ref' => $ref,
            'number' => isset($json['Number']) ? (string) $json['Number'] : null,
            'date' => isset($json['Date']) ? (string) $json['Date'] : null,
            'raw' => $json,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>, posted: bool}
     */
    private function updateEpdStubHttp(string $documentType, string $ref, array $payload): array
    {
        $base = $this->resolveBaseUrl($payload);
        $current = $this->getEpdStubHttp($documentType, $ref, $base);
        if ($current === null) {
            throw new RuntimeException('1С: документ ЭПД не найден для обновления.');
        }

        if ($current['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Документ ЭПД в 1С проведён — изменение из CRM запрещено.',
            ]);
        }

        $body = $this->epdWriteBody($payload);
        $path = $this->epdODataPath($documentType);
        $response = $this->http()->patch($base.$path."(guid'{$ref}')", $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С отказала в обновлении ЭПД: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        if ($json === []) {
            $fresh = $this->getEpdStubHttp($documentType, $ref, $base);
            $json = is_array($fresh['raw'] ?? null) ? $fresh['raw'] : ['Ref_Key' => $ref, 'Posted' => false];
        }

        return [
            'ref' => $ref,
            'number' => isset($json['Number']) ? (string) $json['Number'] : ($current['number'] ?? null),
            'date' => isset($json['Date']) ? (string) $json['Date'] : null,
            'posted' => (bool) ($json['Posted'] ?? false),
            'raw' => $json,
        ];
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    private function getEpdStubHttp(string $documentType, string $ref, ?string $baseUrl = null): ?array
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $path = $this->epdODataPath($documentType);
        $response = $this->http()->get($base.$path."(guid'{$ref}')", [
            '$format' => 'json',
        ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать ЭПД: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $foundRef = (string) ($json['Ref_Key'] ?? $ref);

        return [
            'ref' => $foundRef,
            'number' => isset($json['Number']) ? (string) $json['Number'] : null,
            'posted' => (bool) ($json['Posted'] ?? false),
            'raw' => $json,
        ];
    }

    private function markUnpostedEpdStubDeletedHttp(string $documentType, string $ref, ?string $baseUrl = null): void
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $doc = $this->getEpdStubHttp($documentType, $ref, $base);
        if ($doc === null) {
            return;
        }

        if ($doc['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Документ ЭПД '.$doc['number'].' в 1С проведён — удаление запрещено. Сначала разберите документ в 1С.',
            ]);
        }

        if ((bool) ($doc['raw']['DeletionMark'] ?? false)) {
            return;
        }

        $path = $this->epdODataPath($documentType);
        $response = $this->http()->patch($base.$path."(guid'{$ref}')", [
            'DeletionMark' => true,
        ]);

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось пометить ЭПД на удаление: HTTP '.$response->status().' '.$response->body()
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function epdWriteBody(array $payload): array
    {
        $base = $this->resolveBaseUrl($payload);
        $documentType = (string) ($payload['document_type'] ?? '');

        $body = is_array($payload['odata_stub'] ?? null) ? $payload['odata_stub'] : [];
        unset(
            $body['_crm_counterparty_match'],
            $body['_crm_carrier_match'],
            $body['_crm_organization_ref'],
        );

        if (! empty($payload['organization_ref'])) {
            $body['Организация_Key'] = $payload['organization_ref'];
        }

        $customer = is_array($payload['counterparty'] ?? null) ? $payload['counterparty'] : [];
        $customerRef = $this->resolvePartyRef($customer, $base, 'Заказчик');

        // ЭТрН: в OData нет Контрагент_Key — стороны через СсылкаТитулГрузоотправителя*.
        if ($documentType === 'etrn') {
            $carrierParty = is_array($payload['parties']['carrier'] ?? null) ? $payload['parties']['carrier'] : [];
            $carrierRef = $this->resolvePartyRef($carrierParty, $base, 'Перевозчик', optional: true);

            $contractorType = 'StandardODATA.Catalog_Контрагенты';
            $body['СсылкаТитулГрузоотправителяЗаказчик'] = $customerRef;
            $body['СсылкаТитулГрузоотправителяЗаказчик_Type'] = $contractorType;
            $body['СсылкаТитулГрузоотправителяГрузоотправитель'] = $customerRef;
            $body['СсылкаТитулГрузоотправителяГрузоотправитель_Type'] = $contractorType;
            $body['СсылкаТитулГрузоотправителяГрузополучатель'] = $customerRef;
            $body['СсылкаТитулГрузоотправителяГрузополучатель_Type'] = $contractorType;
            if ($carrierRef !== null) {
                $body['СсылкаТитулГрузоотправителяПеревозчик'] = $carrierRef;
                $body['СсылкаТитулГрузоотправителяПеревозчик_Type'] = $contractorType;
            }

            return $body;
        }

        // Экспедиторская расписка: EntitySet в публикации пока не найден — оставляем минимальный stub.
        $body['Контрагент_Key'] = $customerRef;

        return $body;
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function resolvePartyRef(array $party, ?string $baseUrl, string $label, bool $optional = false): ?string
    {
        $inn = (string) ($party['inn'] ?? '');
        if ($inn === '') {
            if ($optional) {
                return null;
            }

            throw ValidationException::withMessages([
                'one_c' => "Для ЭПД нужен ИНН стороны «{$label}».",
            ]);
        }

        $kpp = isset($party['kpp']) ? (string) $party['kpp'] : '';
        $name = trim((string) ($party['name'] ?? ''));
        if ($name === '') {
            $name = $label.' '.$inn;
        }

        return $this->ensureCounterpartyRef(
            $inn,
            $kpp !== '' ? $kpp : null,
            $name,
            $baseUrl,
            isset($party['phone']) ? (string) $party['phone'] : null,
        );
    }

    private function epdODataPath(string $documentType): string
    {
        return match ($documentType) {
            'etrn' => (string) config('one_c.odata.etrn_path'),
            'expedition_receipt' => (string) config('one_c.odata.expedition_receipt_path'),
            default => throw ValidationException::withMessages([
                'one_c' => 'Неизвестный тип ЭПД для OData: '.$documentType,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>}
     */
    private function createRealizationFake(array $payload): array
    {
        $ref = (string) Str::uuid();

        return [
            'ref' => $ref,
            'number' => 'FAKE-'.substr($ref, 0, 8),
            'date' => (string) ($payload['document_date'] ?? now()->toDateString()),
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Posted' => false,
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>, posted: bool}
     */
    private function updateRealizationFake(string $ref, array $payload): array
    {
        $current = $this->getRealizationFake($ref);
        if ($current === null) {
            throw new RuntimeException('1С: реализация не найдена для обновления.');
        }

        if ($current['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Реализация в 1С проведена — изменение из CRM запрещено.',
            ]);
        }

        return [
            'ref' => $ref,
            'number' => $current['number'],
            'date' => (string) ($payload['document_date'] ?? now()->toDateString()),
            'posted' => false,
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Posted' => false,
                'Number' => $current['number'],
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>}
     */
    private function createRealizationHttp(array $payload): array
    {
        $body = $this->realizationWriteBody($payload);
        $base = $this->resolveBaseUrl($payload);
        $path = (string) config('one_c.odata.realization_path');
        $response = $this->http()->post($base.$path, $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С отказала в создании реализации: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $ref = (string) ($json['Ref_Key'] ?? $json['Ref'] ?? '');
        if ($ref === '') {
            throw new RuntimeException('1С не вернула Ref_Key реализации.');
        }

        return [
            'ref' => $ref,
            'number' => isset($json['Number']) ? (string) $json['Number'] : null,
            'date' => isset($json['Date']) ? (string) $json['Date'] : null,
            'raw' => $json,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, number: string|null, date: string|null, raw: array<string, mixed>, posted: bool}
     */
    private function updateRealizationHttp(string $ref, array $payload): array
    {
        $base = $this->resolveBaseUrl($payload);
        $current = $this->getRealizationHttp($ref, $base);
        if ($current === null) {
            throw new RuntimeException('1С: реализация не найдена для обновления.');
        }

        if ($current['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Реализация в 1С проведена — изменение из CRM запрещено.',
            ]);
        }

        $body = $this->realizationWriteBody($payload);
        $path = (string) config('one_c.odata.realization_path');
        $response = $this->http()->patch($base.$path."(guid'{$ref}')", $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С отказала в обновлении реализации: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        if ($json === []) {
            $fresh = $this->getRealizationHttp($ref, $base);
            $json = is_array($fresh['raw'] ?? null) ? $fresh['raw'] : ['Ref_Key' => $ref, 'Posted' => false];
        }

        return [
            'ref' => $ref,
            'number' => isset($json['Number']) ? (string) $json['Number'] : ($current['number'] ?? null),
            'date' => isset($json['Date']) ? (string) $json['Date'] : null,
            'posted' => (bool) ($json['Posted'] ?? false),
            'raw' => $json,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function realizationWriteBody(array $payload): array
    {
        $base = $this->resolveBaseUrl($payload);

        $counterparty = is_array($payload['counterparty'] ?? null) ? $payload['counterparty'] : [];
        $inn = (string) ($counterparty['inn'] ?? '');
        $kpp = isset($counterparty['kpp']) ? (string) $counterparty['kpp'] : '';

        $name = trim((string) ($counterparty['name'] ?? ''));
        if ($name === '') {
            $name = 'Контрагент '.$inn;
        }
        $counterpartyRef = $this->ensureCounterpartyRef($inn, $kpp !== '' ? $kpp : null, $name, $base);

        $body = is_array($payload['odata_stub'] ?? null) ? $payload['odata_stub'] : [];
        unset($body['_crm_counterparty_match'], $body['_crm_organization_ref']);
        $body['Контрагент_Key'] = $counterpartyRef;

        if (! empty($payload['organization_ref'])) {
            $body['Организация_Key'] = $payload['organization_ref'];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function resolveBaseUrl(?array $payload = null, ?string $baseUrl = null): string
    {
        $fromPayload = is_string($payload['base_url'] ?? null) ? trim((string) $payload['base_url']) : '';
        $explicit = is_string($baseUrl) ? trim($baseUrl) : '';
        $base = rtrim($explicit !== '' ? $explicit : ($fromPayload !== '' ? $fromPayload : (string) config('one_c.base_url', '')), '/');
        if ($base === '') {
            throw ValidationException::withMessages([
                'one_c' => 'Не задан base_url 1С (публикация / ONE_C_BASE_URL).',
            ]);
        }

        return $base;
    }

    /**
     * Банковские документы организации за период (поступления / списания с РС).
     *
     * @param  array{
     *     base_url?: string,
     *     organization_ref?: string,
     *     date_filter_mode?: 'odata'|'client'
     * }  $options
     * @return list<array{
     *     ref: string,
     *     date: string,
     *     direction: 'in'|'out',
     *     amount: float,
     *     number: ?string,
     *     operation: ?string,
     *     counterparty: ?string,
     *     counterparty_inn: ?string,
     *     purpose: ?string,
     *     comment: ?string,
     *     posted: bool
     * }>
     */
    public function listBankMovements(string $dateFrom, string $dateToExclusive, array $options = []): array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->listBankMovementsFake($dateFrom, $dateToExclusive),
            'http' => $this->listBankMovementsHttp($dateFrom, $dateToExclusive, $options),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * Проверка доступности OData публикации.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function ping(?string $baseUrl = null): array
    {
        $driver = (string) config('one_c.driver', 'fake');
        if ($driver === 'fake') {
            return ['ok' => true, 'error' => null];
        }

        $base = rtrim($baseUrl ?? (string) config('one_c.base_url', ''), '/');
        if ($base === '') {
            return ['ok' => false, 'error' => 'Не задан base_url 1С.'];
        }

        try {
            $response = $this->http()->get($base.'/odata/standard.odata/', [
                '$format' => 'json',
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'HTTP '.$response->status()];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * @return list<array{
     *     ref: string,
     *     date: string,
     *     direction: 'in'|'out',
     *     amount: float,
     *     number: ?string,
     *     operation: ?string,
     *     counterparty: ?string,
     *     counterparty_inn: ?string,
     *     purpose: ?string,
     *     comment: ?string,
     *     posted: bool
     * }>
     */
    private function listBankMovementsFake(string $dateFrom, string $dateToExclusive): array
    {
        return [
            [
                'ref' => 'fake-in-1',
                'date' => $dateFrom,
                'direction' => 'in',
                'amount' => 1000.0,
                'number' => '0000-000001',
                'operation' => 'ОплатаПокупателя',
                'counterparty' => 'ТЕСТ КОНТРАГЕНТ ООО',
                'counterparty_inn' => '7700000000',
                'purpose' => 'Оплата по счету 1 за транспортные услуги',
                'comment' => 'fake',
                'posted' => true,
            ],
            [
                'ref' => 'fake-out-1',
                'date' => $dateFrom,
                'direction' => 'out',
                'amount' => 40.0,
                'number' => '0000-000002',
                'operation' => 'КомиссияБанка',
                'counterparty' => 'Сбербанк ПАО',
                'counterparty_inn' => '7707083893',
                'purpose' => 'Комиссия банка',
                'comment' => 'fake',
                'posted' => true,
            ],
        ];
    }

    /**
     * @return list<array{
     *     ref: string,
     *     date: string,
     *     direction: 'in'|'out',
     *     amount: float,
     *     number: ?string,
     *     operation: ?string,
     *     counterparty: ?string,
     *     counterparty_inn: ?string,
     *     purpose: ?string,
     *     comment: ?string,
     *     posted: bool
     * }>
     */
    /**
     * @param  array{
     *     base_url?: string,
     *     organization_ref?: string,
     *     date_filter_mode?: 'odata'|'client'
     * }  $options
     * @return list<array{
     *     ref: string,
     *     date: string,
     *     direction: 'in'|'out',
     *     amount: float,
     *     number: ?string,
     *     operation: ?string,
     *     counterparty: ?string,
     *     counterparty_inn: ?string,
     *     purpose: ?string,
     *     comment: ?string,
     *     posted: bool
     * }>
     */
    private function listBankMovementsHttp(string $dateFrom, string $dateToExclusive, array $options = []): array
    {
        $base = rtrim((string) ($options['base_url'] ?? config('one_c.base_url', '')), '/');
        if ($base === '') {
            throw ValidationException::withMessages([
                'one_c' => 'Не задан ONE_C_BASE_URL для драйвера http.',
            ]);
        }

        $orgRef = (string) ($options['organization_ref'] ?? config('one_c.organization_ref', ''));
        $dateFilterMode = (string) ($options['date_filter_mode'] ?? 'odata');
        $from = $this->odataDateTime($dateFrom);
        $to = $this->odataDateTime($dateToExclusive);

        $incoming = $this->fetchBankDocuments(
            $base,
            (string) config('one_c.odata.bank_incoming_path'),
            'in',
            $from,
            $to,
            $orgRef,
            $dateFilterMode,
        );
        $outgoing = $this->fetchBankDocuments(
            $base,
            (string) config('one_c.odata.bank_outgoing_path'),
            'out',
            $from,
            $to,
            $orgRef,
            $dateFilterMode,
        );

        $rows = array_merge($incoming, $outgoing);
        usort($rows, static function (array $a, array $b): int {
            return [$a['date'], $a['direction'], $a['number'] ?? '']
                <=> [$b['date'], $b['direction'], $b['number'] ?? ''];
        });

        return $rows;
    }

    /**
     * @param  'in'|'out'  $direction
     * @param  'odata'|'client'  $dateFilterMode
     * @return list<array{
     *     ref: string,
     *     date: string,
     *     direction: 'in'|'out',
     *     amount: float,
     *     number: ?string,
     *     operation: ?string,
     *     counterparty: ?string,
     *     counterparty_inn: ?string,
     *     purpose: ?string,
     *     comment: ?string,
     *     posted: bool
     * }>
     */
    private function fetchBankDocuments(
        string $base,
        string $path,
        string $direction,
        string $fromDateTime,
        string $toDateTime,
        string $orgRef,
        string $dateFilterMode = 'odata',
    ): array {
        $query = [
            '$format' => 'json',
            '$top' => 1000,
        ];

        if ($dateFilterMode === 'client') {
            // ponytail: часть ИБ падает на Date+AUTOORDER — тянем пачку и режем период в PHP.
            $filters = [];
            if ($orgRef !== '') {
                $filters[] = "Организация_Key eq guid'{$orgRef}'";
            }
            if ($filters !== []) {
                $query['$filter'] = implode(' and ', $filters);
            }
        } else {
            $filter = "Date ge datetime'{$fromDateTime}' and Date lt datetime'{$toDateTime}'";
            if ($orgRef !== '') {
                $filter .= " and Организация_Key eq guid'{$orgRef}'";
            }
            $query['$filter'] = $filter;
            $query['$orderby'] = 'Date';
        }

        $response = $this->http()->get($base.$path, $query);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось получить банк ('.$direction.'): HTTP '.$response->status().' '.$response->body()
            );
        }

        $value = $response->json('value');
        if (! is_array($value)) {
            return [];
        }

        $fromDate = substr($fromDateTime, 0, 10);
        $toDate = substr($toDateTime, 0, 10);

        $counterpartyCache = [];
        $rows = [];

        foreach ($value as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $ref = (string) ($raw['Ref_Key'] ?? '');
            if ($ref === '') {
                continue;
            }

            $date = substr((string) ($raw['Date'] ?? ''), 0, 10);
            if ($dateFilterMode === 'client') {
                if ($date === '' || $date < $fromDate || $date >= $toDate) {
                    continue;
                }
            }

            $cpRef = (string) ($raw['Контрагент'] ?? $raw['Контрагент_Key'] ?? '');
            $counterpartyName = null;
            $counterpartyInn = null;
            if ($cpRef !== '' && ! str_starts_with($cpRef, '00000000')) {
                if (! array_key_exists($cpRef, $counterpartyCache)) {
                    $counterpartyCache[$cpRef] = $this->fetchCounterpartyMeta($base, $cpRef);
                }
                $meta = $counterpartyCache[$cpRef];
                $counterpartyName = $meta['name'];
                $counterpartyInn = $meta['inn'];
            }

            if ($counterpartyName === null || $counterpartyName === '') {
                $counterpartyName = $this->counterpartyNameFromRequisites($raw);
            }

            $rows[] = [
                'ref' => $ref,
                'date' => $date,
                'direction' => $direction,
                'amount' => round((float) ($raw['СуммаДокумента'] ?? 0), 2),
                'number' => isset($raw['Number']) ? (string) $raw['Number'] : null,
                'operation' => isset($raw['ВидОперации']) ? (string) $raw['ВидОперации'] : null,
                'counterparty' => $counterpartyName,
                'counterparty_inn' => $counterpartyInn,
                'purpose' => isset($raw['НазначениеПлатежа']) ? (string) $raw['НазначениеПлатежа'] : null,
                'comment' => isset($raw['Комментарий']) ? (string) $raw['Комментарий'] : null,
                'posted' => (bool) ($raw['Posted'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @return array{name: ?string, inn: ?string}
     */
    private function fetchCounterpartyMeta(string $base, string $ref): array
    {
        $path = (string) config('one_c.odata.counterparty_path');
        $response = $this->http()->get($base.$path."(guid'{$ref}')", [
            '$format' => 'json',
            '$select' => 'Description,ИНН',
        ]);

        if (! $response->successful()) {
            return ['name' => null, 'inn' => null];
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return [
            'name' => isset($json['Description']) ? (string) $json['Description'] : null,
            'inn' => isset($json['ИНН']) ? (string) $json['ИНН'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function counterpartyNameFromRequisites(array $raw): ?string
    {
        $rows = $raw['РеквизитыКонтрагента'] ?? null;
        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['Реквизит'] ?? '');
            if ($key === 'Наименование' || $key === 'НаименованиеРасширен') {
                $val = trim((string) ($row['Значение'] ?? ''));
                if ($val !== '') {
                    return $val;
                }
            }
        }

        return null;
    }

    private function odataDateTime(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date.'T00:00:00';
        }

        return $date;
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    public function getRealization(string $ref, ?string $baseUrl = null): ?array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->getRealizationFake($ref),
            'http' => $this->getRealizationHttp($ref, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * Счёт на оплату покупателю (Document_СчетНаОплатуПокупателю).
     *
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    public function getBuyerInvoice(string $ref): ?array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->getBuyerInvoiceFake($ref),
            'http' => $this->getBuyerInvoiceHttp($ref),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * Снимает непроведённую реализацию: пометка удаления в 1С (DeletionMark).
     * Жёсткий DELETE у учётки OData часто упирается в права последовательностей.
     * Проведённую — ValidationException.
     */
    public function deleteUnpostedRealization(string $ref, ?string $baseUrl = null): void
    {
        $driver = (string) config('one_c.driver', 'fake');

        match ($driver) {
            'fake' => $this->deleteUnpostedRealizationFake($ref),
            'http' => $this->markUnpostedRealizationDeletedHttp($ref, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    private function getRealizationFake(string $ref): ?array
    {
        if ($ref === '' || $ref === 'missing') {
            return null;
        }

        $posted = $ref === '11111111-1111-1111-1111-111111111111';
        $invoiceRef = str_starts_with($ref, 'real-with-invoice')
            ? 'invoice-'.substr($ref, -8)
            : null;

        return [
            'ref' => $ref,
            'number' => 'FAKE-'.substr($ref, 0, 8),
            'posted' => $posted,
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Posted' => $posted,
                'Number' => 'FAKE-'.substr($ref, 0, 8),
                'Контрагент_Key' => 'cp-fake',
                'ЭтоУниверсальныйДокумент' => str_starts_with($ref, 'real-with-edo'),
                'ВидЭлектронногоДокумента' => 'АктВыполненныхРабот',
                'СчетНаОплатуПокупателю_Key' => $invoiceRef ?? '00000000-0000-0000-0000-000000000000',
            ],
        ];
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    private function getBuyerInvoiceFake(string $ref): ?array
    {
        if ($ref === '' || $ref === 'missing' || str_starts_with($ref, '00000000')) {
            return null;
        }

        return [
            'ref' => $ref,
            'number' => '0000-000075',
            'posted' => true,
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'Number' => '0000-000075',
                'Posted' => true,
            ],
        ];
    }

    private function deleteUnpostedRealizationFake(string $ref): void
    {
        $doc = $this->getRealizationFake($ref);
        if ($doc === null) {
            return;
        }

        if ($doc['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Реализация в 1С проведена — удаление запрещено. Сначала разберите документ в 1С.',
            ]);
        }
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    private function getRealizationHttp(string $ref, ?string $baseUrl = null): ?array
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $path = (string) config('one_c.odata.realization_path');
        $response = $this->http()->get($base.$path."(guid'{$ref}')", [
            '$format' => 'json',
        ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать реализацию: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $foundRef = (string) ($json['Ref_Key'] ?? $ref);

        return [
            'ref' => $foundRef,
            'number' => isset($json['Number']) ? (string) $json['Number'] : null,
            'posted' => (bool) ($json['Posted'] ?? false),
            'raw' => $json,
        ];
    }

    /**
     * @return array{ref: string, number: ?string, posted: bool, raw: array<string, mixed>}|null
     */
    private function getBuyerInvoiceHttp(string $ref): ?array
    {
        $base = (string) config('one_c.base_url', '');
        if ($base === '') {
            throw ValidationException::withMessages([
                'one_c' => 'Не задан ONE_C_BASE_URL для драйвера http.',
            ]);
        }

        $path = (string) config('one_c.odata.buyer_invoice_path');
        $response = $this->http()->get($base.$path."(guid'{$ref}')", [
            '$format' => 'json',
            '$select' => 'Ref_Key,Number,Posted,Date,СуммаДокумента,Комментарий',
        ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать счёт покупателя: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $foundRef = (string) ($json['Ref_Key'] ?? $ref);

        return [
            'ref' => $foundRef,
            'number' => isset($json['Number']) ? (string) $json['Number'] : null,
            'posted' => (bool) ($json['Posted'] ?? false),
            'raw' => $json,
        ];
    }

    private function markUnpostedRealizationDeletedHttp(string $ref, ?string $baseUrl = null): void
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $doc = $this->getRealizationHttp($ref, $base);
        if ($doc === null) {
            return;
        }

        if ($doc['posted']) {
            throw ValidationException::withMessages([
                'one_c' => 'Реализация '.$doc['number'].' в 1С проведена — удаление запрещено. Сначала разберите документ в 1С.',
            ]);
        }

        if ((bool) ($doc['raw']['DeletionMark'] ?? false)) {
            return;
        }

        $path = (string) config('one_c.odata.realization_path');
        $response = $this->http()->patch($base.$path."(guid'{$ref}')", [
            'DeletionMark' => true,
        ]);

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось пометить реализацию на удаление: HTTP '.$response->status().' '.$response->body()
            );
        }
    }

    /**
     * Найти контрагента или создать минимальную карточку в 1С.
     */
    public function ensureCounterpartyRef(
        string $inn,
        ?string $kpp,
        string $name,
        ?string $baseUrl = null,
        ?string $phone = null,
    ): string {
        $existing = $this->findCounterpartyRef($inn, $kpp, $baseUrl);
        if ($existing !== null) {
            $this->repairCounterpartyLegalFormIfNeeded($existing, $inn, $name, $baseUrl);
            $this->ensureCounterpartyPhone($existing, $phone, $baseUrl);

            return $existing;
        }

        return $this->createCounterparty($inn, $kpp, $name, $baseUrl, $phone);
    }

    public function findCounterpartyRef(string $inn, ?string $kpp = null, ?string $baseUrl = null): ?string
    {
        $driver = (string) config('one_c.driver', 'fake');
        if ($driver === 'fake') {
            return (string) Str::uuid();
        }

        $base = rtrim($baseUrl ?? (string) config('one_c.base_url', ''), '/');
        if ($base === '') {
            return null;
        }

        // В публикации БП `ИНН eq` в $filter запрещён; substringof работает.
        $innEscaped = str_replace("'", "''", $inn);
        $filter = "substringof('{$innEscaped}',ИНН)";

        $path = (string) config('one_c.odata.counterparty_path');
        $response = $this->http()->get($base.$path, [
            '$format' => 'json',
            '$filter' => $filter,
            '$top' => 20,
            '$select' => 'Ref_Key,ИНН,КПП,Description',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $value = $response->json('value');
        if (! is_array($value) || $value === []) {
            return null;
        }

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowInn = trim((string) ($row['ИНН'] ?? ''));
            if ($rowInn !== $inn) {
                continue;
            }

            if ($kpp !== null && $kpp !== '') {
                $rowKpp = trim((string) ($row['КПП'] ?? ''));
                if ($rowKpp !== $kpp) {
                    continue;
                }
            }

            $ref = $row['Ref_Key'] ?? null;

            return is_string($ref) && $ref !== '' ? $ref : null;
        }

        return null;
    }

    /**
     * Создать контрагента в 1С (минимальные реквизиты для реализации).
     * ИНН 12 → физлицо + ИндивидуальныйПредприниматель (иначе 1С ругается на «длинный ИНН»).
     */
    public function createCounterparty(
        string $inn,
        ?string $kpp,
        string $name,
        ?string $baseUrl = null,
        ?string $phone = null,
    ): string {
        $driver = (string) config('one_c.driver', 'fake');
        if ($driver === 'fake') {
            return (string) Str::uuid();
        }

        $base = rtrim($baseUrl ?? (string) config('one_c.base_url', ''), '/');
        if ($base === '') {
            throw ValidationException::withMessages([
                'one_c' => 'Не задан ONE_C_BASE_URL для создания контрагента.',
            ]);
        }

        $body = $this->counterpartyWriteBody($inn, $kpp, $name);
        $phoneRow = $this->counterpartyPhoneRow($phone, $base);
        if ($phoneRow !== null) {
            $body['КонтактнаяИнформация'] = [$phoneRow];
        }

        $path = (string) config('one_c.odata.counterparty_path');
        $response = $this->http()->post($base.$path, $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось создать контрагента: HTTP '.$response->status().' '.$response->body()
            );
        }

        $ref = (string) ($response->json('Ref_Key') ?? '');
        if ($ref === '') {
            throw new RuntimeException('1С не вернула Ref_Key контрагента.');
        }

        return $ref;
    }

    /**
     * Дописать телефон в карточку контрагента, если его ещё нет.
     * Нужен ЭТрН: форма 1С ругается «Не заполнен номер телефона контрагента».
     */
    public function ensureCounterpartyPhone(string $ref, ?string $phone, ?string $baseUrl = null): void
    {
        $phone = trim((string) ($phone ?? ''));
        if ($phone === '' || $ref === '') {
            return;
        }

        $driver = (string) config('one_c.driver', 'fake');
        if ($driver !== 'http') {
            return;
        }

        $base = rtrim($baseUrl ?? (string) config('one_c.base_url', ''), '/');
        if ($base === '') {
            return;
        }

        $path = (string) config('one_c.odata.counterparty_path');
        $url = $base.$path."(guid'{$ref}')";
        $current = $this->http()->get($url, ['$format' => 'json']);
        if (! $current->successful()) {
            return;
        }

        $existing = $current->json('КонтактнаяИнформация');
        $rows = is_array($existing) ? $existing : [];
        if ($this->counterpartyContactRowsHavePhone($rows)) {
            return;
        }

        $phoneRow = $this->counterpartyPhoneRow($phone, $base);
        if ($phoneRow === null) {
            return;
        }

        $phoneRow['LineNumber'] = (string) (count($rows) + 1);
        $rows[] = $phoneRow;

        $response = $this->http()->patch($url, [
            'КонтактнаяИнформация' => $rows,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось записать телефон контрагента: HTTP '.$response->status().' '.$response->body()
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function counterpartyContactRowsHavePhone(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = (string) ($row['Тип'] ?? '');
            $number = trim((string) ($row['НомерТелефона'] ?? ''));
            if ($type === 'Телефон' || $number !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function counterpartyPhoneRow(?string $phone, string $baseUrl): ?array
    {
        $phone = trim((string) ($phone ?? ''));
        if ($phone === '') {
            return null;
        }

        $kindRef = $this->resolveCounterpartyPhoneKindRef($baseUrl);
        if ($kindRef === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $country = '7';
        $area = '';
        $local = $digits;
        if (str_starts_with($digits, '7') && strlen($digits) >= 11) {
            $area = substr($digits, 1, 3);
            $local = substr($digits, 4);
        } elseif (str_starts_with($digits, '8') && strlen($digits) >= 11) {
            $area = substr($digits, 1, 3);
            $local = substr($digits, 4);
        }

        $valueJson = json_encode([
            'version' => 5,
            'value' => $phone,
            'type' => 'Телефон',
            'countryCode' => $country,
            'areaCode' => $area,
            'number' => $local,
        ], JSON_UNESCAPED_UNICODE);

        $xml = '<КонтактнаяИнформация xmlns="http://www.v8.1c.ru/ssl/contactinfo"'
            .' xmlns:xs="http://www.w3.org/2001/XMLSchema"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' Представление="'.htmlspecialchars($phone, ENT_QUOTES | ENT_XML1).'">'
            .'<Состав xsi:type="НомерТелефона" КодСтраны="'.$country.'"'
            .' КодГорода="'.htmlspecialchars($area, ENT_QUOTES | ENT_XML1).'"'
            .' Номер="'.htmlspecialchars($local, ENT_QUOTES | ENT_XML1).'"/>'
            .'</КонтактнаяИнформация>';

        return [
            'LineNumber' => '1',
            'Тип' => 'Телефон',
            'Вид_Key' => $kindRef,
            'Представление' => $phone,
            'ЗначенияПолей' => $xml,
            'Страна' => '',
            'Регион' => '',
            'Город' => '',
            'АдресЭП' => '',
            'ДоменноеИмяСервера' => '',
            'НомерТелефона' => $phone,
            'НомерТелефонаБезКодов' => $local,
            'ВидДляСписка_Key' => $kindRef,
            'Значение' => is_string($valueJson) ? $valueJson : $phone,
        ];
    }

    private function resolveCounterpartyPhoneKindRef(string $baseUrl): ?string
    {
        $configured = trim((string) config('one_c.odata.counterparty_phone_kind_ref', ''));
        if ($configured !== '') {
            return $configured;
        }

        $kindsPath = (string) config(
            'one_c.odata.contact_info_kinds_path',
            '/odata/standard.odata/Catalog_ВидыКонтактнойИнформации'
        );
        $response = $this->http()->get($baseUrl.$kindsPath, [
            '$format' => 'json',
            '$filter' => "PredefinedDataName eq 'ТелефонКонтрагента'",
            '$top' => 1,
            '$select' => 'Ref_Key,PredefinedDataName',
        ]);

        if ($response->successful()) {
            $ref = $response->json('value.0.Ref_Key');
            if (is_string($ref) && $ref !== '') {
                return $ref;
            }
        }

        // Предопределённый вид из типовой БП (совпал на prod/test ИБ Автоальянс).
        return '7b727858-320e-11f1-acc9-b69a48ddb3f4';
    }

    /**
     * @return array<string, mixed>
     */
    private function counterpartyWriteBody(string $inn, ?string $kpp, string $name): array
    {
        $innDigits = preg_replace('/\D+/', '', $inn) ?? '';
        $name = trim($name);
        if ($name === '') {
            $name = 'Контрагент '.$innDigits;
        }

        // ponytail: 12 цифр = ИП/физлицо в РФ; без ЮрФизЛицо=ФизическоеЛицо 1С принимает как организацию и ругается на ИНН.
        if (strlen($innDigits) === 12) {
            $description = preg_replace('/^\s*ИП[\s.\-–—]+/ui', '', $name) ?? $name;
            $description = trim((string) $description);
            if ($description === '') {
                $description = $name;
            }
            $fullName = preg_match('/^\s*ИП\b/ui', $name) === 1
                ? $name
                : 'ИП '.$description;

            return [
                'Description' => $description,
                'НаименованиеПолное' => $fullName,
                'ИНН' => $innDigits,
                'ЮридическоеФизическоеЛицо' => 'ФизическоеЛицо',
                'ИндивидуальныйПредприниматель' => true,
            ];
        }

        $body = [
            'Description' => $name,
            'НаименованиеПолное' => $name,
            'ИНН' => $innDigits,
            'ЮридическоеФизическоеЛицо' => 'ЮридическоеЛицо',
            'ИндивидуальныйПредприниматель' => false,
        ];
        if ($kpp !== null && $kpp !== '') {
            $body['КПП'] = $kpp;
        }

        return $body;
    }

    /**
     * Если ИП уже создан как «организация» — поправить тип, не трогая ИНН.
     */
    private function repairCounterpartyLegalFormIfNeeded(
        string $ref,
        string $inn,
        string $name,
        ?string $baseUrl = null,
    ): void {
        $innDigits = preg_replace('/\D+/', '', $inn) ?? '';
        if (strlen($innDigits) !== 12) {
            return;
        }

        $driver = (string) config('one_c.driver', 'fake');
        if ($driver !== 'http') {
            return;
        }

        $base = rtrim($baseUrl ?? (string) config('one_c.base_url', ''), '/');
        if ($base === '') {
            return;
        }

        $path = (string) config('one_c.odata.counterparty_path');
        $url = $base.$path."(guid'{$ref}')";
        $current = $this->http()->get($url, [
            '$format' => 'json',
            '$select' => 'ЮридическоеФизическоеЛицо,ИндивидуальныйПредприниматель,ИНН,НаименованиеПолное',
        ]);
        if (! $current->successful()) {
            return;
        }

        $type = (string) ($current->json('ЮридическоеФизическоеЛицо') ?? '');
        $isIp = (bool) ($current->json('ИндивидуальныйПредприниматель') ?? false);
        if ($type === 'ФизическоеЛицо' && $isIp) {
            return;
        }

        $body = $this->counterpartyWriteBody($innDigits, null, $name);
        // Не затираем Description при repair — только тип/полное имя/ИНН.
        unset($body['Description']);

        $response = $this->http()->patch($url, $body);
        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось исправить тип контрагента (ИП): HTTP '.$response->status().' '.$response->body()
            );
        }
    }

    /**
     * Связи объект учёта (реализация / СФ) ↔ электронный документ ЭДО.
     *
     * @return list<array{
     *     edo_ref: string,
     *     edo_type: string,
     *     object_ref: string,
     *     object_type: string,
     *     actual: bool
     * }>
     */
    public function findEdoLinksForAccountingObject(string $objectRef, ?string $baseUrl = null): array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->findEdoLinksForAccountingObjectFake($objectRef),
            'http' => $this->findEdoLinksForAccountingObjectHttp($objectRef, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @return array{
     *     ref: string,
     *     number: ?string,
     *     sent_at: ?string,
     *     signed_at: ?string,
     *     regulation_type: ?string,
     *     amount: float,
     *     counterparty_ref: ?string,
     *     raw: array<string, mixed>
     * }|null
     */
    public function getOutgoingEdoDocument(string $ref, ?string $baseUrl = null): ?array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->getOutgoingEdoDocumentFake($ref),
            'http' => $this->getOutgoingEdoDocumentHttp($ref, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @return array{state: ?string, changed_at: ?string, raw: array<string, mixed>}|null
     */
    public function getEdoDocumentState(string $edoRef, ?string $baseUrl = null): ?array
    {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->getEdoDocumentStateFake($edoRef),
            'http' => $this->getEdoDocumentStateHttp($edoRef, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * СФ выданный по основанию = реализация (поиск в PHP — OData filter по ДокументОснование ненадёжен).
     *
     * @return list<array{ref: string, number: ?string, issued: bool, issue_method: int, issued_at: ?string, raw: array<string, mixed>}>
     */
    public function findIssuedInvoiceFacturasForRealization(
        string $realizationRef,
        string $counterpartyRef,
        ?string $baseUrl = null,
    ): array {
        $driver = (string) config('one_c.driver', 'fake');

        return match ($driver) {
            'fake' => $this->findIssuedInvoiceFacturasForRealizationFake($realizationRef),
            'http' => $this->findIssuedInvoiceFacturasForRealizationHttp($realizationRef, $counterpartyRef, $baseUrl),
            default => throw ValidationException::withMessages([
                'one_c' => "Неизвестный драйвер 1С: {$driver}.",
            ]),
        };
    }

    /**
     * @return list<array{edo_ref: string, edo_type: string, object_ref: string, object_type: string, actual: bool}>
     */
    private function findEdoLinksForAccountingObjectFake(string $objectRef): array
    {
        if (! str_starts_with($objectRef, 'real-with-edo')) {
            return [];
        }

        return [[
            'edo_ref' => 'edo-out-'.substr($objectRef, -8),
            'edo_type' => 'StandardODATA.Document_ЭлектронныйДокументИсходящийЭДО',
            'object_ref' => $objectRef,
            'object_type' => 'StandardODATA.Document_РеализацияТоваровУслуг',
            'actual' => true,
        ]];
    }

    /**
     * @return list<array{edo_ref: string, edo_type: string, object_ref: string, object_type: string, actual: bool}>
     */
    private function findEdoLinksForAccountingObjectHttp(string $objectRef, ?string $baseUrl = null): array
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $path = (string) config('one_c.odata.edo_accounting_objects_path');
        $escaped = str_replace("'", "''", $objectRef);
        $response = $this->http()->get($base.$path, [
            '$format' => 'json',
            '$filter' => "ОбъектУчета eq '{$escaped}'",
            '$top' => 20,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать связи ЭДО: HTTP '.$response->status().' '.$response->body()
            );
        }

        $rows = [];
        foreach ($response->json('value') ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $edoRef = trim((string) ($raw['ЭлектронныйДокумент'] ?? ''));
            if ($edoRef === '') {
                continue;
            }
            $rows[] = [
                'edo_ref' => $edoRef,
                'edo_type' => (string) ($raw['ЭлектронныйДокумент_Type'] ?? ''),
                'object_ref' => (string) ($raw['ОбъектУчета'] ?? $objectRef),
                'object_type' => (string) ($raw['ОбъектУчета_Type'] ?? ''),
                'actual' => (bool) ($raw['Актуальный'] ?? true),
            ];
        }

        return $rows;
    }

    /**
     * @return array{ref: string, number: ?string, sent_at: ?string, signed_at: ?string, regulation_type: ?string, amount: float, counterparty_ref: ?string, raw: array<string, mixed>}|null
     */
    private function getOutgoingEdoDocumentFake(string $ref): ?array
    {
        if ($ref === '' || $ref === 'missing' || str_starts_with($ref, '00000000')) {
            return null;
        }

        return [
            'ref' => $ref,
            'number' => 'УПД-100',
            'sent_at' => '2026-08-01T12:00:00',
            'signed_at' => '2026-08-01T11:55:00',
            'regulation_type' => 'УПД',
            'amount' => 70000.0,
            'counterparty_ref' => 'cp-fake',
            'raw' => [
                'driver' => 'fake',
                'Ref_Key' => $ref,
                'НомерДокумента' => 'УПД-100',
                'ДатаОтправки' => '2026-08-01T12:00:00',
                'ТипРегламента' => 'УПД',
            ],
        ];
    }

    /**
     * @return array{ref: string, number: ?string, sent_at: ?string, signed_at: ?string, regulation_type: ?string, amount: float, counterparty_ref: ?string, raw: array<string, mixed>}|null
     */
    private function getOutgoingEdoDocumentHttp(string $ref, ?string $baseUrl = null): ?array
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $path = (string) config('one_c.odata.edo_outgoing_document_path');
        $response = $this->http()->get($base.$path."(guid'{$ref}')", [
            '$format' => 'json',
        ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать исходящий ЭДО: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return [
            'ref' => (string) ($json['Ref_Key'] ?? $ref),
            'number' => $this->nullIfBlank($json['НомерДокумента'] ?? null),
            'sent_at' => $this->nullIfEmptyOneCDate($json['ДатаОтправки'] ?? null),
            'signed_at' => $this->nullIfEmptyOneCDate($json['ДатаПодписания'] ?? null),
            'regulation_type' => $this->nullIfBlank($json['ТипРегламента'] ?? null),
            'amount' => round((float) ($json['СуммаДокумента'] ?? 0), 2),
            'counterparty_ref' => $this->nullIfBlank($json['Контрагент'] ?? null),
            'raw' => $json,
        ];
    }

    /**
     * @return array{state: ?string, changed_at: ?string, raw: array<string, mixed>}|null
     */
    private function getEdoDocumentStateFake(string $edoRef): ?array
    {
        if ($edoRef === '' || str_starts_with($edoRef, 'edo-pending')) {
            return ['state' => 'ТребуетсяПодписание', 'changed_at' => null, 'raw' => ['driver' => 'fake']];
        }

        return [
            'state' => 'ОбменЗавершен',
            'changed_at' => '2026-08-02T10:00:00',
            'raw' => ['driver' => 'fake', 'Состояние' => 'ОбменЗавершен'],
        ];
    }

    /**
     * @return array{state: ?string, changed_at: ?string, raw: array<string, mixed>}|null
     */
    private function getEdoDocumentStateHttp(string $edoRef, ?string $baseUrl = null): ?array
    {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $path = (string) config('one_c.odata.edo_document_states_path');
        $escaped = str_replace("'", "''", $edoRef);
        $response = $this->http()->get($base.$path, [
            '$format' => 'json',
            '$filter' => "ЭлектронныйДокумент eq '{$escaped}'",
            '$top' => 5,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать состояние ЭДО: HTTP '.$response->status().' '.$response->body()
            );
        }

        $value = $response->json('value');
        if (! is_array($value) || $value === []) {
            return null;
        }

        /** @var array<string, mixed> $row */
        $row = $value[0];

        return [
            'state' => $this->nullIfBlank($row['Состояние'] ?? null),
            'changed_at' => $this->nullIfEmptyOneCDate($row['ДатаИзменения'] ?? null),
            'raw' => $row,
        ];
    }

    /**
     * @return list<array{ref: string, number: ?string, issued: bool, issue_method: int, issued_at: ?string, raw: array<string, mixed>}>
     */
    private function findIssuedInvoiceFacturasForRealizationFake(string $realizationRef): array
    {
        if (! str_starts_with($realizationRef, 'real-with-edo')) {
            return [];
        }

        return [[
            'ref' => 'sf-'.substr($realizationRef, -8),
            'number' => '0000-000100',
            'issued' => true,
            'issue_method' => 1,
            'issued_at' => '2026-08-01',
            'raw' => ['driver' => 'fake'],
        ]];
    }

    /**
     * @return list<array{ref: string, number: ?string, issued: bool, issue_method: int, issued_at: ?string, raw: array<string, mixed>}>
     */
    private function findIssuedInvoiceFacturasForRealizationHttp(
        string $realizationRef,
        string $counterpartyRef,
        ?string $baseUrl = null,
    ): array {
        $base = $this->resolveBaseUrl(null, $baseUrl);
        $path = (string) config('one_c.odata.issued_invoice_factura_path');
        $query = [
            '$format' => 'json',
            '$top' => 30,
            '$orderby' => 'Date desc',
        ];
        if ($counterpartyRef !== '' && ! str_starts_with($counterpartyRef, '00000000')) {
            $query['$filter'] = "Контрагент_Key eq guid'{$counterpartyRef}' and DeletionMark eq false";
        }

        $response = $this->http()->get($base.$path, $query);
        if (! $response->successful()) {
            throw new RuntimeException(
                '1С: не удалось прочитать СФ выданные: HTTP '.$response->status().' '.$response->body()
            );
        }

        $rows = [];
        foreach ($response->json('value') ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            if ((string) ($raw['ДокументОснование'] ?? '') !== $realizationRef) {
                continue;
            }
            $rows[] = [
                'ref' => (string) ($raw['Ref_Key'] ?? ''),
                'number' => $this->nullIfBlank($raw['Number'] ?? null),
                'issued' => (bool) ($raw['Выставлен'] ?? false),
                'issue_method' => (int) ($raw['КодСпособаВыставления'] ?? 0),
                'issued_at' => $this->nullIfEmptyOneCDate($raw['ДатаВыставления'] ?? null),
                'raw' => $raw,
            ];
        }

        return $rows;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullIfEmptyOneCDate(mixed $value): ?string
    {
        $raw = $this->nullIfBlank($value);
        if ($raw === null) {
            return null;
        }
        if (str_starts_with($raw, '0001-01-01')) {
            return null;
        }

        return $raw;
    }

    private function http(): PendingRequest
    {
        $timeout = (int) config('one_c.timeout_seconds', 30);
        $username = (string) config('one_c.username', '');
        $password = (string) config('one_c.password', '');

        $request = Http::timeout($timeout)
            ->acceptJson()
            ->asJson();

        if ($username !== '') {
            $request = $request->withBasicAuth($username, $password);
        }

        return $request;
    }
}
