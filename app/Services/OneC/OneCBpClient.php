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
    public function ensureCounterpartyRef(string $inn, ?string $kpp, string $name, ?string $baseUrl = null): string
    {
        $existing = $this->findCounterpartyRef($inn, $kpp, $baseUrl);
        if ($existing !== null) {
            $this->repairCounterpartyLegalFormIfNeeded($existing, $inn, $name, $baseUrl);

            return $existing;
        }

        return $this->createCounterparty($inn, $kpp, $name, $baseUrl);
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
    public function createCounterparty(string $inn, ?string $kpp, string $name, ?string $baseUrl = null): string
    {
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
