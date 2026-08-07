<?php

namespace App\Services\Contractor;

use App\Models\Contractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Публичные сниппеты по названию/ИНН + опционально сайт из карточки (без ФНС). Soft-fail.
 */
class ContractorWebFactsCollector
{
    /**
     * @return array{
     *     enabled: bool,
     *     query: string|null,
     *     website: string|null,
     *     website_excerpt: string|null,
     *     snippets: list<array{title: string, url: string, snippet: string}>,
     *     error: string|null
     * }
     */
    public function collect(Contractor $contractor): array
    {
        if (! (bool) config('contractor_enrichment.web.enabled', true)) {
            return [
                'enabled' => false,
                'query' => null,
                'website' => null,
                'website_excerpt' => null,
                'snippets' => [],
                'error' => null,
            ];
        }

        $query = $this->buildQuery($contractor);
        $website = $this->normalizeWebsite($contractor->website);
        $error = null;
        $snippets = [];
        $websiteExcerpt = null;

        try {
            if ($query !== null) {
                $snippets = $this->searchDuckDuckGo($query);
            }
        } catch (Throwable $e) {
            $error = Str::limit($e->getMessage(), 240);
        }

        if ($website !== null) {
            try {
                $websiteExcerpt = $this->fetchWebsiteExcerpt($website);
            } catch (Throwable $e) {
                $error = $error ?? Str::limit($e->getMessage(), 240);
            }
        }

        return [
            'enabled' => true,
            'query' => $query,
            'website' => $website,
            'website_excerpt' => $websiteExcerpt,
            'snippets' => $snippets,
            'error' => $error,
        ];
    }

    private function buildQuery(Contractor $contractor): ?string
    {
        $name = trim((string) ($contractor->name ?? ''));
        $inn = preg_replace('/\D+/', '', (string) ($contractor->inn ?? '')) ?: null;

        if ($name === '' && $inn === null) {
            return null;
        }

        return implode(' ', array_filter([$name !== '' ? $name : null, $inn]));
    }

    private function normalizeWebsite(mixed $website): ?string
    {
        $raw = trim((string) ($website ?? ''));
        if ($raw === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $raw)) {
            $raw = 'https://'.$raw;
        }

        return filter_var($raw, FILTER_VALIDATE_URL) ? $raw : null;
    }

    private function fetchWebsiteExcerpt(string $url): ?string
    {
        $timeout = max(3, (int) config('contractor_enrichment.web.timeout_seconds', 8));
        $ua = (string) config('contractor_enrichment.web.user_agent', 'CRM-v5-ContractorEnrichment/1.0');

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => $ua,
                'Accept' => 'text/html',
            ])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($response->body()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : Str::limit($text, 600);
    }

    /**
     * @return list<array{title: string, url: string, snippet: string}>
     */
    private function searchDuckDuckGo(string $query): array
    {
        $timeout = max(3, (int) config('contractor_enrichment.web.timeout_seconds', 8));
        $max = max(1, (int) config('contractor_enrichment.web.max_snippets', 5));
        $ua = (string) config('contractor_enrichment.web.user_agent', 'CRM-v5-ContractorEnrichment/1.0');

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => $ua,
                'Accept' => 'text/html',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
            ])
            ->asForm()
            ->post('https://html.duckduckgo.com/html/', [
                'q' => $query,
            ]);

        if (! $response->successful()) {
            return [];
        }

        return $this->parseResultHtml($response->body(), $max);
    }

    /**
     * @return list<array{title: string, url: string, snippet: string}>
     */
    private function parseResultHtml(string $html, int $max): array
    {
        $snippets = [];

        if (preg_match_all(
            '/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/su',
            $html,
            $linkMatches,
            PREG_SET_ORDER,
        ) === false || $linkMatches === []) {
            return [];
        }

        preg_match_all(
            '/<(?:a|td)[^>]+class="[^"]*result__snippet[^"]*"[^>]*>(.*?)<\/(?:a|td)>/su',
            $html,
            $snippetMatches,
            PREG_SET_ORDER,
        );

        foreach ($linkMatches as $index => $match) {
            if (count($snippets) >= $max) {
                break;
            }

            $url = html_entity_decode($this->unwrapDuckRedirect($match[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim(html_entity_decode(strip_tags($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $snippetRaw = $snippetMatches[$index][1] ?? '';
            $snippet = trim(html_entity_decode(strip_tags((string) $snippetRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($title === '' || $url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }

            if (preg_match('/nalog\.gov|egrul|fns\.ru/iu', $url) === 1) {
                continue;
            }

            $snippets[] = [
                'title' => Str::limit($title, 160),
                'url' => Str::limit($url, 500, ''),
                'snippet' => Str::limit($snippet, 400),
            ];
        }

        return $snippets;
    }

    private function unwrapDuckRedirect(string $href): string
    {
        $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (str_contains($href, 'uddg=')) {
            $parts = parse_url($href);
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
                if (! empty($query['uddg']) && is_string($query['uddg'])) {
                    return urldecode($query['uddg']);
                }
            }
        }

        return $href;
    }
}
