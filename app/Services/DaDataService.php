<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DaDataService
{
    private string $baseUrl = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs';

    public function suggestAddress(string $query, int $count = 8): array
    {
        if (mb_strlen($query) < 3 || ! $this->isConfigured()) {
            return [];
        }

        return $this->request('/suggest/address', [
            'query' => $query,
            'count' => $count,
            'language' => 'ru',
        ]);
    }

    public function suggestParty(string $query, int $count = 5): array
    {
        if (mb_strlen($query) < 2 || ! $this->isConfigured()) {
            return [];
        }

        return $this->request('/suggest/party', [
            'query' => $query,
            'count' => $count,
        ]);
    }

    public function suggestBank(string $bik): array
    {
        $normalizedBik = preg_replace('/\D/u', '', $bik) ?? '';
        if (strlen($normalizedBik) !== 9 || ! $this->isConfigured()) {
            return [];
        }

        return $this->request('/findById/bank', [
            'query' => $normalizedBik,
            'count' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalizeAddress(array $response): array
    {
        $data = $response['data'] ?? $response;

        return [
            'city' => $data['city'] ?? $data['settlement'] ?? null,
            'region' => $data['region_with_type'] ?? $data['region'] ?? null,
            'street' => $data['street_with_type'] ?? $data['street'] ?? null,
            'house' => $data['house_type_full'] ?? $data['house'] ?? null,
            'coordinates' => [
                'lat' => $data['geo_lat'] ?? null,
                'lng' => $data['geo_lon'] ?? null,
            ],
            'kladr_id' => $data['kladr_id'] ?? null,
            'fias_id' => $data['fias_id'] ?? null,
            'formatted_address' => $this->formatAddress($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function formatAddress(array $response): string
    {
        $parts = array_filter([
            $response['city'] ?? $response['settlement'] ?? null,
            $response['street_with_type'] ?? $response['street'] ?? null,
            $response['house'] ?? null,
        ], static fn (?string $value): bool => filled($value));

        return implode(', ', $parts);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.dadata.token'));
    }

    private function request(string $endpoint, array $payload): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('services.dadata.token'),
                'X-Secret' => (string) config('services.dadata.secret'),
                'Accept' => 'application/json',
            ])->post($this->baseUrl.$endpoint, $payload);

            if ($response->failed()) {
                Log::warning('DaData request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return $response->json('suggestions', []);
        } catch (\Throwable $exception) {
            Log::warning('DaData request exception', [
                'endpoint' => $endpoint,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
