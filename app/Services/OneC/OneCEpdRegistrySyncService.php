<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\OneCEpdRegistryEntry;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pull InformationRegister_РеестрЭПД → one_c_epd_registry_entries.
 */
final class OneCEpdRegistrySyncService
{
    public function __construct(
        private readonly OneCPublicationCatalog $publications,
        private readonly OneCEpdDocumentTypeResolver $typeResolver,
    ) {}

    /**
     * @return array{
     *     publications: int,
     *     fetched: int,
     *     upserted: int,
     *     errors: int,
     *     skipped: bool,
     *     reason?: string
     * }
     */
    public function sync(?string $publicationCode = null, ?int $top = null): array
    {
        $stats = [
            'publications' => 0,
            'fetched' => 0,
            'upserted' => 0,
            'errors' => 0,
            'skipped' => false,
        ];

        if (! (bool) config('one_c.enabled')) {
            $stats['skipped'] = true;
            $stats['reason'] = 'one_c.disabled';

            return $stats;
        }

        if (! Schema::hasTable('one_c_epd_registry_entries')) {
            $stats['skipped'] = true;
            $stats['reason'] = 'table_missing';

            return $stats;
        }

        if ((string) config('one_c.driver') === 'fake') {
            $stats['skipped'] = true;
            $stats['reason'] = 'fake_driver';

            return $stats;
        }

        $pubs = $publicationCode !== null && $publicationCode !== ''
            ? [$this->publications->get($publicationCode)]
            : $this->publications->all();

        $limit = $top !== null && $top > 0
            ? $top
            : (int) config('one_c.epd_registry.sync_top', 200);

        foreach ($pubs as $pub) {
            $stats['publications']++;
            try {
                $result = $this->syncPublication($pub, $limit);
                $stats['fetched'] += $result['fetched'];
                $stats['upserted'] += $result['upserted'];
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('one_c.epd_registry_sync_failed', [
                    'publication' => $pub['code'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array{
     *     code: string,
     *     base_url: string
     * }  $pub
     * @return array{fetched: int, upserted: int}
     */
    private function syncPublication(array $pub, int $top): array
    {
        $baseUrl = rtrim((string) ($pub['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return ['fetched' => 0, 'upserted' => 0];
        }

        $path = (string) config(
            'one_c.odata.epd_registry_path',
            '/odata/standard.odata/InformationRegister_РеестрЭПД'
        );

        $response = $this->http()->get($baseUrl.$path, [
            '$format' => 'json',
            '$top' => $top,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'HTTP '.$response->status().' '.$response->body()
            );
        }

        $rows = data_get($response->json(), 'value', []);
        if (! is_array($rows)) {
            return ['fetched' => 0, 'upserted' => 0];
        }

        $typeCache = [];
        $partyCache = [];
        $upserted = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $documentRef = trim((string) ($row['Ссылка'] ?? ''));
            if ($documentRef === '' || $documentRef === '00000000-0000-0000-0000-000000000000') {
                continue;
            }

            $documentRefType = trim((string) ($row['Ссылка_Type'] ?? ''));
            $typeRefKey = trim((string) ($row['ТипСсылки_Key'] ?? ''));
            $resolved = $this->resolveType($baseUrl, $documentRefType, $typeRefKey, $typeCache);

            $shipper = $this->resolveParty($baseUrl, $row['Грузоотправитель'] ?? null, $partyCache);
            $consignee = $this->resolveParty($baseUrl, $row['Грузополучатель'] ?? null, $partyCache);
            $carrier = $this->resolveParty($baseUrl, $row['Перевозчик'] ?? null, $partyCache);

            $attributes = [
                'document_ref_type' => $documentRefType !== '' ? $documentRefType : null,
                'type_ref_key' => $typeRefKey !== '' ? $typeRefKey : null,
                'document_type' => $resolved['code'],
                'document_type_label' => $resolved['label'],
                'epd_number' => $this->nullableString($row['НомерЭПД'] ?? null),
                'epd_date' => $this->toDate($row['ДатаЭПД'] ?? null),
                'ib_number' => $this->nullableString($row['НомерДокументаИБ'] ?? null),
                'ib_date' => $this->toDateTime($row['ДатаДокументаИБ'] ?? null),
                'current_step' => $this->nullableString($row['ТекущийШаг'] ?? null),
                'current_step_done' => (bool) ($row['ТекущийШагВыполнен'] ?? false),
                'participant_role' => isset($row['РольУчастника']) && is_numeric($row['РольУчастника'])
                    ? (int) $row['РольУчастника']
                    : null,
                'organization_ref' => $this->nullableUuid($row['Организация_Key'] ?? null),
                'shipper_ref' => $shipper['ref'],
                'shipper_name' => $shipper['name'],
                'shipper_inn' => $shipper['inn'],
                'consignee_ref' => $consignee['ref'],
                'consignee_name' => $consignee['name'],
                'consignee_inn' => $consignee['inn'],
                'carrier_ref' => $carrier['ref'],
                'carrier_name' => $carrier['name'],
                'carrier_inn' => $carrier['inn'],
                'posted' => (bool) ($row['Проведен'] ?? false),
                'deletion_mark' => (bool) ($row['ПометкаУдаления'] ?? false),
                'raw_payload' => $row,
                'last_synced_at' => now(),
            ];

            OneCEpdRegistryEntry::query()->updateOrCreate(
                [
                    'publication_code' => (string) $pub['code'],
                    'document_ref' => $documentRef,
                ],
                $attributes,
            );
            $upserted++;
        }

        return ['fetched' => count($rows), 'upserted' => $upserted];
    }

    /**
     * @param  array<string, array{code: string, label: string}>  $cache
     * @return array{code: string, label: string}
     */
    private function resolveType(string $baseUrl, string $documentRefType, string $typeRefKey, array &$cache): array
    {
        $cacheKey = $typeRefKey !== '' ? $typeRefKey : $documentRefType;
        if ($cacheKey !== '' && isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $fullName = null;
        $synonym = null;
        if ($typeRefKey !== '') {
            try {
                $meta = $this->http()->get(
                    $baseUrl."/odata/standard.odata/Catalog_ИдентификаторыОбъектовМетаданных(guid'{$typeRefKey}')",
                    ['$format' => 'json', '$select' => 'ПолноеИмя,Синоним,Description,Имя'],
                );
                if ($meta->successful()) {
                    $json = $meta->json() ?? [];
                    $fullName = isset($json['ПолноеИмя']) ? (string) $json['ПолноеИмя'] : null;
                    $synonym = isset($json['Синоним'])
                        ? (string) $json['Синоним']
                        : (isset($json['Description']) ? (string) $json['Description'] : null);
                }
            } catch (Throwable) {
                // ignore — fall back to ref type string
            }
        }

        $resolved = $this->typeResolver->resolve($documentRefType, $fullName, $synonym);
        if ($cacheKey !== '') {
            $cache[$cacheKey] = $resolved;
        }

        return $resolved;
    }

    /**
     * @param  array<string, array{ref: ?string, name: ?string, inn: ?string}>  $cache
     * @return array{ref: ?string, name: ?string, inn: ?string}
     */
    private function resolveParty(string $baseUrl, mixed $raw, array &$cache): array
    {
        $ref = is_string($raw) ? trim($raw) : '';
        if ($ref === '' || $ref === '00000000-0000-0000-0000-000000000000') {
            return ['ref' => null, 'name' => null, 'inn' => null];
        }

        if (isset($cache[$ref])) {
            return $cache[$ref];
        }

        foreach ([
            (string) config('one_c.odata.counterparty_path'),
            (string) config(
                'one_c.odata.organization_path',
                '/odata/standard.odata/Catalog_Организации'
            ),
        ] as $path) {
            if ($path === '') {
                continue;
            }
            try {
                $response = $this->http()->get($baseUrl.$path."(guid'{$ref}')", [
                    '$format' => 'json',
                    '$select' => 'Description,ИНН',
                ]);
                if ($response->successful()) {
                    $json = $response->json() ?? [];
                    $out = [
                        'ref' => $ref,
                        'name' => $this->nullableString($json['Description'] ?? null),
                        'inn' => $this->nullableString($json['ИНН'] ?? null),
                    ];
                    $cache[$ref] = $out;

                    return $out;
                }
            } catch (Throwable) {
                // try Catalog_Организации after Catalog_Контрагенты
            }
        }

        $out = ['ref' => $ref, 'name' => null, 'inn' => null];
        $cache[$ref] = $out;

        return $out;
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('one_c.username'),
            (string) config('one_c.password'),
        )
            ->timeout((int) config('one_c.timeout_seconds', 30))
            ->acceptJson();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }

    private function nullableUuid(mixed $value): ?string
    {
        $s = $this->nullableString($value);
        if ($s === null || $s === '00000000-0000-0000-0000-000000000000') {
            return null;
        }

        return $s;
    }

    private function toDate(mixed $value): ?string
    {
        $s = $this->nullableString($value);
        if ($s === null || str_starts_with($s, '0001-01-01')) {
            return null;
        }

        return substr($s, 0, 10);
    }

    private function toDateTime(mixed $value): ?string
    {
        $s = $this->nullableString($value);
        if ($s === null || str_starts_with($s, '0001-01-01')) {
            return null;
        }

        return $s;
    }
}
