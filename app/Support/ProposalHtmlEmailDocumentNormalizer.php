<?php

namespace App\Support;

/**
 * Полный HTML письма (EmailMaker / .eml) → фрагмент body + CSS для GrapesJS и wrapDocument().
 *
 * @phpstan-type NormalizedEmailHtml array{
 *     body: string,
 *     css: string,
 *     font_urls: list<string>
 * }
 */
final class ProposalHtmlEmailDocumentNormalizer
{
    /**
     * @return NormalizedEmailHtml
     */
    public static function normalize(string $html, ?string $existingCss = null): array
    {
        $html = trim($html);
        $cssChunks = [];
        $existing = trim((string) $existingCss);
        if ($existing !== '') {
            $cssChunks[] = $existing;
        }

        $fontUrls = self::extractFontStylesheetUrls($html);

        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $styleMatches) > 0) {
            foreach ($styleMatches[1] as $chunk) {
                $chunk = trim((string) $chunk);
                if ($chunk !== '') {
                    $cssChunks[] = $chunk;
                }
            }
            $html = (string) preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        }

        $body = self::extractBodyInnerHtml($html);

        foreach ($fontUrls as $url) {
            $import = "@import url('".str_replace("'", "\\'", $url)."');";
            if (! str_contains(implode("\n", $cssChunks), $url)) {
                array_unshift($cssChunks, $import);
            }
        }

        $css = self::uniqueCssChunks($cssChunks);

        return [
            'body' => trim($body),
            'css' => $css,
            'font_urls' => $fontUrls,
        ];
    }

    public static function extractBodyInnerHtml(string $html): string
    {
        if (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        $withoutDoctype = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $withoutHtml = (string) preg_replace('/<\/?html\b[^>]*>/i', '', $withoutDoctype);
        $withoutHead = (string) preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $withoutHtml);

        return trim($withoutHead);
    }

    /**
     * @return list<string>
     */
    public static function extractFontStylesheetUrls(string $html): array
    {
        $urls = [];

        if (preg_match_all('/<link\b[^>]*>/i', $html, $linkMatches) < 1) {
            return [];
        }

        foreach ($linkMatches[0] as $tag) {
            if (! preg_match('/rel\s*=\s*["\']stylesheet["\']/i', $tag)) {
                continue;
            }

            if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $tag, $hrefMatch) !== 1) {
                continue;
            }

            $href = trim((string) $hrefMatch[1]);
            if ($href === '') {
                continue;
            }

            $isFont = str_contains(strtolower($href), 'fonts.googleapis.com')
                || str_contains(strtolower($href), 'fonts.gstatic.com')
                || str_contains(strtolower($tag), 'font');

            if ($isFont) {
                $urls[] = $href;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  list<string>  $chunks
     */
    private static function uniqueCssChunks(array $chunks): string
    {
        $seen = [];
        $out = [];

        foreach ($chunks as $chunk) {
            $key = md5($chunk);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $chunk;
        }

        return implode("\n", $out);
    }
}
