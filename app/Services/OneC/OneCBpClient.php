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
        $base = (string) config('one_c.base_url', '');
        if ($base === '') {
            throw ValidationException::withMessages([
                'one_c' => 'Не задан ONE_C_BASE_URL для драйвера http.',
            ]);
        }

        $counterparty = is_array($payload['counterparty'] ?? null) ? $payload['counterparty'] : [];
        $inn = (string) ($counterparty['inn'] ?? '');
        $kpp = isset($counterparty['kpp']) ? (string) $counterparty['kpp'] : '';

        $counterpartyRef = $this->findCounterpartyRef($inn, $kpp !== '' ? $kpp : null);
        if ($counterpartyRef === null) {
            throw ValidationException::withMessages([
                'one_c' => "Контрагент с ИНН {$inn}".($kpp !== '' ? " / КПП {$kpp}" : '').' не найден в 1С.',
            ]);
        }

        $body = is_array($payload['odata_stub'] ?? null) ? $payload['odata_stub'] : [];
        unset($body['_crm_counterparty_match'], $body['_crm_organization_ref']);
        $body['Контрагент_Key'] = $counterpartyRef;

        if (! empty($payload['organization_ref'])) {
            $body['Организация_Key'] = $payload['organization_ref'];
        }

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

    public function findCounterpartyRef(string $inn, ?string $kpp = null): ?string
    {
        $driver = (string) config('one_c.driver', 'fake');
        if ($driver === 'fake') {
            return (string) Str::uuid();
        }

        $base = (string) config('one_c.base_url', '');
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
