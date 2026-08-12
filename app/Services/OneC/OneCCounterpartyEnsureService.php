<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Contractor;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Найти или создать контрагента в 1С по ИНН/КПП (CRM → 1С, односторонне).
 */
final class OneCCounterpartyEnsureService
{
    public function __construct(
        private readonly OneCBpClient $oneC,
    ) {}

    public function ensureRef(string $inn, ?string $kpp, string $name, ?string $baseUrl = null): string
    {
        return $this->oneC->ensureCounterpartyRef($inn, $kpp, $name, $baseUrl);
    }

    /**
     * @return array{ref: string, created_or_found: true}|null null если нет ИНН / 1С выключена
     */
    public function ensureForContractor(Contractor $contractor, ?string $baseUrl = null): ?array
    {
        if (! (bool) config('one_c.enabled', false)) {
            return null;
        }

        $inn = preg_replace('/\D+/', '', (string) ($contractor->inn ?? '')) ?? '';
        if ($inn === '' || (strlen($inn) !== 10 && strlen($inn) !== 12)) {
            return null;
        }

        $kppRaw = trim((string) ($contractor->kpp ?? ''));
        $kpp = $kppRaw !== '' ? (preg_replace('/\D+/', '', $kppRaw) ?? '') : null;
        if ($kpp === '') {
            $kpp = null;
        }

        if (strlen($inn) === 10 && ($kpp === null || strlen($kpp) !== 9)) {
            Log::info('one_c.counterparty_ensure_skipped_no_kpp', [
                'contractor_id' => $contractor->id,
                'inn' => $inn,
            ]);

            return null;
        }

        $name = trim((string) ($contractor->name ?? $contractor->full_name ?? ''));
        if ($name === '') {
            $name = 'Контрагент '.$inn;
        }

        $ref = $this->ensureRef($inn, $kpp, $name, $baseUrl);

        return ['ref' => $ref, 'created_or_found' => true];
    }

    public function ensureOrderCustomer(Order $order, ?string $baseUrl = null): ?array
    {
        $order->loadMissing(['client:id,name,full_name,inn,kpp']);
        $client = $order->client;
        if ($client === null) {
            return null;
        }

        try {
            return $this->ensureForContractor($client, $baseUrl);
        } catch (Throwable $e) {
            Log::warning('one_c.counterparty_ensure_failed', [
                'order_id' => $order->id,
                'contractor_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
