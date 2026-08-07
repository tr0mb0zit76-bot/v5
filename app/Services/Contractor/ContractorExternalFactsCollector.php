<?php

namespace App\Services\Contractor;

use App\Models\Contractor;
use App\Services\Checko\ContractorScoringService;
use App\Services\DaDataService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Лёгкие снимки уже существующих интеграций (без новых провайдеров). Soft-fail.
 */
class ContractorExternalFactsCollector
{
    public function __construct(
        private readonly DaDataService $daData,
        private readonly ContractorScoringService $scoring,
    ) {}

    /**
     * @return array{dadata: array<string, mixed>|null, checko: array<string, mixed>|null}
     */
    public function collect(Contractor $contractor): array
    {
        return [
            'dadata' => $this->dadataSnapshot($contractor),
            'checko' => $this->checkoSnapshot($contractor),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dadataSnapshot(Contractor $contractor): ?array
    {
        $inn = preg_replace('/\D+/', '', (string) ($contractor->inn ?? ''));
        if ($inn === null || ! in_array(strlen($inn), [10, 12], true)) {
            return null;
        }

        try {
            $suggestions = $this->daData->suggestParty($inn, 1);
            $row = is_array($suggestions[0] ?? null) ? $suggestions[0] : null;
            if ($row === null) {
                return null;
            }

            $party = is_array($row['data'] ?? null) ? $row['data'] : [];
            $address = is_array($party['address'] ?? null) ? (string) ($party['address']['value'] ?? '') : '';

            return array_filter([
                'value' => $row['value'] ?? null,
                'inn' => $party['inn'] ?? null,
                'kpp' => $party['kpp'] ?? null,
                'ogrn' => $party['ogrn'] ?? null,
                'okved' => $party['okved'] ?? ($party['okveds'][0]['code'] ?? null),
                'okved_name' => $party['okveds'][0]['name'] ?? null,
                'address' => $address !== '' ? $address : null,
                'status' => $party['state']['status'] ?? null,
            ], static fn (mixed $v): bool => $v !== null && $v !== '');
        } catch (Throwable $e) {
            Log::debug('contractor enrichment dadata soft-fail', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkoSnapshot(Contractor $contractor): ?array
    {
        if ((string) config('checko.api_key') === '') {
            return null;
        }

        try {
            $payload = $this->scoring->buildPayload($contractor, false);
            if (! ($payload['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => $payload['error'] ?? 'Checko unavailable',
                ];
            }

            return [
                'ok' => true,
                'grade' => $payload['grade'] ?? null,
                'tier' => $payload['tier'] ?? null,
                'tier_label' => $payload['tier_label'] ?? null,
                'score' => $payload['score'] ?? null,
                'status_text' => $payload['status_text'] ?? null,
                'from_cache' => (bool) ($payload['checko_from_cache'] ?? false),
                'summary' => $payload['summary'] ?? null,
                'company_name' => $payload['company_name'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::debug('contractor enrichment checko soft-fail', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
