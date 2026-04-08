<?php

namespace App\Services\Checko;

use App\Models\Contractor;
use App\Services\ContractorCreditService;
use Illuminate\Support\Facades\Cache;

class ContractorScoringService
{
    public function __construct(
        private readonly ContractorCreditService $creditService,
        private readonly CheckoDataNormalizer $normalizer,
        private readonly ContractorScoringCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Contractor $contractor, bool $refresh): array
    {
        $innRaw = $contractor->inn;
        if (! $this->isValidInn($innRaw)) {
            return [
                'ok' => false,
                'error' => 'Укажите корректный ИНН (10 или 12 цифр) в карточке контрагента.',
            ];
        }

        $inn = preg_replace('/\D+/', '', (string) $innRaw);
        if ($inn === null || $inn === '') {
            return [
                'ok' => false,
                'error' => 'Укажите корректный ИНН (10 или 12 цифр) в карточке контрагента.',
            ];
        }

        $apiKey = (string) config('checko.api_key');
        if ($apiKey === '') {
            return [
                'ok' => false,
                'error' => 'Интеграция Checko не настроена: задайте CHECKO_API_KEY в .env.',
            ];
        }

        $cacheKey = 'checko:bundle:'.md5($inn);
        $ttl = (int) config('checko.cache_ttl_seconds');

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $fromCache = false;

        if (! $refresh && Cache::has($cacheKey)) {
            /** @var array<string, array{ok: bool, status: int, body: array<string, mixed>|null}> $bundle */
            $bundle = Cache::get($cacheKey);
            $fromCache = true;
        } else {
            $client = new CheckoApiClient(
                (string) config('checko.api_base'),
                $apiKey,
                (int) config('checko.timeout_seconds'),
            );

            $bundle = $client->fetchBundle($inn);

            if (($bundle['company']['ok'] ?? false) && is_array($bundle['company']['body'] ?? null)) {
                Cache::put($cacheKey, $bundle, $ttl);
            }
        }

        if (! ($bundle['company']['ok'] ?? false) || ! is_array($bundle['company']['body'] ?? null)) {
            return [
                'ok' => false,
                'error' => 'Не удалось получить основные данные компании из Checko (проверьте ИНН и ключ API).',
            ];
        }

        $normalized = $this->normalizer->normalize($bundle);

        $currentDebt = $this->creditService->currentDebtForContractor($contractor->id);
        $debtLimitReached = $this->creditService->isBlockedByDebtLimit($contractor, $currentDebt);

        $internal = [
            'debt_limit_reached' => $debtLimitReached,
            'stop_on_limit' => (bool) ($contractor->stop_on_limit ?? false),
            'current_debt' => $currentDebt,
            'debt_limit' => $contractor->debt_limit,
        ];

        $result = $this->calculator->calculate($normalized, $internal);

        $statusText = is_string($normalized['status_text'] ?? null) ? $normalized['status_text'] : null;
        $egrStatus = $this->calculator->classifyEgrStatus($statusText);

        return [
            'ok' => true,
            'inn' => $inn,
            'company_name' => $normalized['company_name'] ?? null,
            'status_text' => $statusText,
            'egr_status' => $egrStatus,
            'checko_from_cache' => $fromCache,
            'score' => $result['score'],
            'grade' => $result['grade'],
            'recommended_postpayment_days' => $result['recommended_postpayment_days'],
            'recommended_debt_limit_rub' => $result['recommended_debt_limit_rub'] ?? 0,
            'factors' => $result['factors'],
            'summary' => $result['summary'],
            'meta' => [
                'enforcement_count' => $normalized['enforcement_count'],
                'enforcement_sum_rub' => $normalized['enforcement_sum_rub'],
                'defendant_cases' => $normalized['defendant_cases'],
            ],
        ];
    }

    private function isValidInn(?string $inn): bool
    {
        if ($inn === null || trim($inn) === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $inn);
        if ($digits === null || $digits === '') {
            return false;
        }

        $len = strlen($digits);

        return $len === 10 || $len === 12;
    }
}
