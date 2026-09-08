<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Contractor;
use App\Models\OneCEpdRegistryEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderDocumentEdoAcknowledgementService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * После pull РеестрЭПД: GET ЭТрН → document_meta, авто-связь с заказом, ЭДО-отметка carrier/etrn.
 */
final class OneCEpdEtrnStatusSyncService
{
    public function __construct(
        private readonly OneCPublicationCatalog $publications,
        private readonly OneCEpdEtrnExchangeDetector $exchangeDetector,
        private readonly OneCEpdRegistryLinkService $linkService,
        private readonly OrderDocumentEdoAcknowledgementService $edoAcknowledgements,
    ) {}

    /**
     * @return array{
     *     checked: int,
     *     enriched: int,
     *     linked: int,
     *     acknowledged: int,
     *     skipped_no_exchange: int,
     *     skipped_no_order: int,
     *     skipped_manual: int,
     *     skipped_unchanged: int,
     *     errors: int,
     *     skipped: bool,
     *     reason?: string
     * }
     */
    public function sync(?string $publicationCode = null, ?int $limit = null): array
    {
        $stats = [
            'checked' => 0,
            'enriched' => 0,
            'linked' => 0,
            'acknowledged' => 0,
            'skipped_no_exchange' => 0,
            'skipped_no_order' => 0,
            'skipped_manual' => 0,
            'skipped_unchanged' => 0,
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

        $query = OneCEpdRegistryEntry::query()
            ->where('document_type', OneCEpdRegistryEntry::TYPE_ETRN)
            ->where('deletion_mark', false)
            ->orderByDesc('id');

        if ($publicationCode !== null && $publicationCode !== '') {
            $query->where('publication_code', $publicationCode);
        }

        $max = $limit !== null && $limit > 0
            ? $limit
            : (int) config('one_c.epd_registry.etrn_enrich_top', 50);

        if ($max > 0) {
            $query->limit($max);
        }

        $partyCache = [];

        foreach ($query->get() as $entry) {
            $stats['checked']++;

            try {
                $result = $this->syncEntry($entry, $partyCache);
                $stats[$result]++;
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('one_c.epd_etrn_status_sync_failed', [
                    'entry_id' => $entry->id,
                    'document_ref' => $entry->document_ref,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, array{ref: ?string, name: ?string, inn: ?string}>  $partyCache
     * @return 'enriched'|'linked'|'acknowledged'|'skipped_no_exchange'|'skipped_no_order'|'skipped_manual'|'skipped_unchanged'
     */
    private function syncEntry(OneCEpdRegistryEntry $entry, array &$partyCache): string
    {
        $pub = $this->publications->get((string) $entry->publication_code);
        $baseUrl = rtrim((string) ($pub['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return 'skipped_unchanged';
        }

        $document = $this->fetchEtrnDocument($baseUrl, (string) $entry->document_ref);
        if ($document === null) {
            return 'skipped_unchanged';
        }

        $meta = $this->exchangeDetector->metaFromDocument($document);
        $meta = $this->enrichPartyInns($baseUrl, $meta, $partyCache);

        $previousMeta = is_array($entry->document_meta) ? $entry->document_meta : [];
        $entry->forceFill([
            'document_meta' => $meta,
            'current_step' => $meta['current_step'] ?? $entry->current_step,
            'current_step_done' => $meta['current_step_done'],
        ])->save();

        $metaChanged = $previousMeta !== $meta;
        $outcome = $metaChanged ? 'enriched' : 'skipped_unchanged';

        if (! ($meta['exchange_with_carrier'] ?? false)) {
            return $metaChanged ? 'enriched' : 'skipped_no_exchange';
        }

        $order = $this->resolveOrder($entry, $meta);
        if ($order === null) {
            return 'skipped_no_order';
        }

        if ($entry->order_id === null) {
            $systemUser = $this->systemUser();
            $this->linkService->linkAutomatically($entry, $order, $systemUser);
            $entry->refresh();
            $outcome = 'linked';
        }

        $documentNumber = $this->documentNumberForAck($entry, $meta);
        if ($documentNumber === '') {
            return $outcome === 'linked' ? 'linked' : ($metaChanged ? 'enriched' : 'skipped_unchanged');
        }

        $ack = $this->edoAcknowledgements->upsertFromOneC($order, [
            'party' => 'carrier',
            'document_type' => 'etrn',
            'slot_key' => 'etrn',
            'contractor_id' => 0,
            'document_number' => $documentNumber,
            'document_date' => $meta['waybill_date']
                ?? ($entry->epd_date?->toDateString())
                ?? null,
        ]);

        if ($ack['skipped_manual']) {
            return 'skipped_manual';
        }

        if ($ack['changed']) {
            return 'acknowledged';
        }

        return $outcome === 'linked' ? 'linked' : ($metaChanged ? 'enriched' : 'skipped_unchanged');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEtrnDocument(string $baseUrl, string $documentRef): ?array
    {
        $path = (string) config(
            'one_c.odata.etrn_path',
            '/odata/standard.odata/Document_ЭлектроннаяТранспортнаяНакладная'
        );

        $response = $this->http()->get($baseUrl.$path."(guid'{$documentRef}')", [
            '$format' => 'json',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, array{ref: ?string, name: ?string, inn: ?string}>  $partyCache
     * @return array<string, mixed>
     */
    private function enrichPartyInns(string $baseUrl, array $meta, array &$partyCache): array
    {
        foreach (['customer_ref' => 'customer', 'carrier_ref' => 'carrier', 'shipper_ref' => 'shipper', 'consignee_ref' => 'consignee'] as $refKey => $prefix) {
            $ref = isset($meta[$refKey]) ? (string) $meta[$refKey] : '';
            if ($ref === '') {
                continue;
            }
            $party = $this->resolveParty($baseUrl, $ref, $partyCache);
            $meta[$prefix.'_inn'] = $party['inn'];
            $meta[$prefix.'_name'] = $party['name'];
        }

        return $meta;
    }

    /**
     * @param  array<string, array{ref: ?string, name: ?string, inn: ?string}>  $cache
     * @return array{ref: ?string, name: ?string, inn: ?string}
     */
    private function resolveParty(string $baseUrl, string $ref, array &$cache): array
    {
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
                // try next catalog
            }
        }

        $out = ['ref' => $ref, 'name' => null, 'inn' => null];
        $cache[$ref] = $out;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveOrder(OneCEpdRegistryEntry $entry, array $meta): ?Order
    {
        if ($entry->order_id !== null) {
            return Order::query()->find((int) $entry->order_id);
        }

        $customerInn = $this->digits((string) ($meta['customer_inn'] ?? ''));
        $carrierInn = $this->digits((string) ($meta['carrier_inn'] ?? $entry->carrier_inn ?? ''));

        if ($customerInn === '' || $carrierInn === '') {
            return null;
        }

        $customerIds = Contractor::query()
            ->where('inn', $customerInn)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $carrierIds = Contractor::query()
            ->where('inn', $carrierInn)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($customerIds === [] || $carrierIds === []) {
            return null;
        }

        $candidates = Order::query()
            ->whereIn('customer_id', $customerIds)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->filter(fn (Order $order): bool => $this->orderHasCarrier($order, $carrierIds))
            ->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // Несколько кандидатов — не угадываем (кроме явного совпадения номера ТН с order_number).
        $waybill = trim((string) ($meta['waybill_number'] ?? ''));
        if ($waybill !== '' && $candidates->isNotEmpty()) {
            $byNumber = $candidates->filter(function (Order $order) use ($waybill): bool {
                $num = (string) ($order->order_number ?? '');

                return $num !== '' && (str_ends_with($num, $waybill) || $num === $waybill);
            });
            if ($byNumber->count() === 1) {
                return $byNumber->first();
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $carrierIds
     */
    private function orderHasCarrier(Order $order, array $carrierIds): bool
    {
        if ($order->carrier_id !== null && in_array((int) $order->carrier_id, $carrierIds, true)) {
            return true;
        }

        $performers = is_array($order->performers) ? $order->performers : [];
        foreach ($performers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['contractor_id']) ? (int) $row['contractor_id'] : 0;
            if ($id > 0 && in_array($id, $carrierIds, true)) {
                return true;
            }
            $slots = is_array($row['split_carriers'] ?? null) ? $row['split_carriers'] : [];
            foreach ($slots as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $slotId = isset($slot['contractor_id']) ? (int) $slot['contractor_id'] : 0;
                if ($slotId > 0 && in_array($slotId, $carrierIds, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function documentNumberForAck(OneCEpdRegistryEntry $entry, array $meta): string
    {
        foreach ([
            $entry->epd_number,
            $meta['waybill_number'] ?? null,
            $entry->ib_number,
        ] as $candidate) {
            $s = trim((string) ($candidate ?? ''));
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    private function systemUser(): ?User
    {
        $id = config('one_c.system_user.user_id');
        if (is_numeric($id) && (int) $id > 0) {
            return User::query()->find((int) $id);
        }

        $email = trim((string) config('one_c.system_user.email', ''));
        if ($email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return null;
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

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
