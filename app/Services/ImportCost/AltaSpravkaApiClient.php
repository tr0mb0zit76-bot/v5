<?php

declare(strict_types=1);

namespace App\Services\ImportCost;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class AltaSpravkaApiClient
{
    public function isConfigured(): bool
    {
        return filled($this->login()) && filled($this->password());
    }

    public function buildSecret(string $tnCode): string
    {
        $normalized = preg_replace('/\s+/', '', $tnCode) ?? $tnCode;

        return md5($normalized.':'.$this->login().':'.md5($this->password()));
    }

    /**
     * @return array{body: string, status: int}|null
     */
    public function fetchGoodInfo(
        string $tnCode,
        ?string $date = null,
        ?string $countryCode = null,
        bool $certificate = false,
        bool $spCertificate = false,
    ): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', $tnCode) ?? $tnCode;
        $date ??= now()->format('Y-m-d');
        $countryCode ??= (string) config('import_cost_calculator.alta.default_country_code', '156');

        $query = [
            'tncode' => $normalized,
            'login' => $this->login(),
            'secret' => $this->buildSecret($normalized),
            'country' => $countryCode,
            'certificate' => $certificate ? 1 : 0,
            'sp_certificate' => $spCertificate ? 1 : 0,
            'date' => $date,
        ];

        $url = rtrim((string) config('import_cost_calculator.alta.base_url', 'https://www.alta.ru/tnved/xml/'), '/').'/';
        $timeout = (int) config('import_cost_calculator.alta.timeout_seconds', 30);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'AvtoalyansCrmImportCost/1.0 (+internal sync)',
                    'Accept' => 'application/xml, text/xml, */*',
                ])
                ->get($url, $query);
        } catch (ConnectionException) {
            return null;
        }

        return [
            'body' => $response->body(),
            'status' => $response->status(),
        ];
    }

    private function login(): string
    {
        return (string) config('import_cost_calculator.alta.login', '');
    }

    private function password(): string
    {
        return (string) config('import_cost_calculator.alta.password', '');
    }
}
