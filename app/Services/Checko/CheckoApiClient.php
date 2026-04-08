<?php

namespace App\Services\Checko;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class CheckoApiClient
{
    public function __construct(
        private readonly string $apiBase,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * Параллельные запросы к основным разделам Checko v2.
     *
     * @return array<string, array{ok: bool, status: int, body: array<string, mixed>|null}>
     */
    public function fetchBundle(string $inn): array
    {
        $query = [
            'key' => $this->apiKey,
            'inn' => $inn,
        ];

        $responses = Http::pool(function (Pool $pool) use ($query): array {
            return [
                $pool->as('company')->timeout($this->timeoutSeconds)->get("{$this->apiBase}/company", $query),
                $pool->as('finances')->timeout($this->timeoutSeconds)->get("{$this->apiBase}/finances", $query),
                $pool->as('enforcements')->timeout($this->timeoutSeconds)->get("{$this->apiBase}/enforcements", $query),
                $pool->as('legal_defendant')->timeout($this->timeoutSeconds)->get("{$this->apiBase}/legal-cases", array_merge($query, ['role' => 'defendant'])),
                $pool->as('legal_plaintiff')->timeout($this->timeoutSeconds)->get("{$this->apiBase}/legal-cases", array_merge($query, ['role' => 'plaintiff'])),
            ];
        });

        $bundle = [];

        foreach (['company', 'finances', 'enforcements', 'legal_defendant', 'legal_plaintiff'] as $key) {
            $response = $responses[$key] ?? null;
            if ($response === null) {
                $bundle[$key] = ['ok' => false, 'status' => 0, 'body' => null];

                continue;
            }

            if (! $response->successful()) {
                $bundle[$key] = ['ok' => false, 'status' => $response->status(), 'body' => null];

                continue;
            }

            $json = $response->json();
            $bundle[$key] = [
                'ok' => true,
                'status' => $response->status(),
                'body' => is_array($json) ? $json : null,
            ];
        }

        return $bundle;
    }
}
