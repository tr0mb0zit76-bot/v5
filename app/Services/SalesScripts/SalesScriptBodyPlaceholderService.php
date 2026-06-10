<?php

namespace App\Services\SalesScripts;

/**
 * Плейсхолдеры в тексте шага: {client_name}, {routes}.
 */
final class SalesScriptBodyPlaceholderService
{
    private const string PLACEHOLDER_PATTERN = '/\{([a-z][a-z0-9_]*)\}/';

    /**
     * @return list<string>
     */
    public function extractFieldCodes(string $body): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $body, $matches);
        $codes = $matches[1] ?? [];

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<string>  $captureFieldCodes
     * @param  array<string, string>  $valuesByCode
     * @param  array<string, string>  $labelsByCode
     * @return list<array{
     *     type: string,
     *     content?: string,
     *     code?: string,
     *     label?: string,
     *     value?: string,
     *     empty_label?: string
     * }>
     */
    public function buildSegments(
        string $body,
        array $captureFieldCodes,
        array $valuesByCode,
        array $labelsByCode,
    ): array {
        if (! preg_match(self::PLACEHOLDER_PATTERN, $body)) {
            return [];
        }

        $captureLookup = array_fill_keys($captureFieldCodes, true);
        $emittedCaptureCodes = [];
        $segments = [];
        $parts = preg_split(
            '/(\{[a-z][a-z0-9_]*\})/',
            $body,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        foreach ($parts as $part) {
            if (preg_match('/^\{([a-z][a-z0-9_]*)\}$/', $part, $match) !== 1) {
                $segments[] = [
                    'type' => 'text',
                    'content' => $part,
                ];

                continue;
            }

            $code = $match[1];
            $label = $labelsByCode[$code] ?? $code;

            $value = trim((string) ($valuesByCode[$code] ?? ''));

            if (isset($captureLookup[$code]) && ! isset($emittedCaptureCodes[$code])) {
                $emittedCaptureCodes[$code] = true;
                $segments[] = [
                    'type' => 'capture',
                    'code' => $code,
                    'label' => $label,
                    'value' => $value,
                ];

                continue;
            }

            $segments[] = [
                'type' => 'reference',
                'code' => $code,
                'label' => $label,
                'value' => $value,
                'empty_label' => '['.$label.' не указано]',
            ];
        }

        return $segments;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    public function segmentsToPlainText(array $segments): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            $parts[] = match ($segment['type'] ?? '') {
                'text' => (string) ($segment['content'] ?? ''),
                'capture' => trim((string) ($segment['value'] ?? '')) !== ''
                    ? (string) $segment['value']
                    : '['.((string) ($segment['label'] ?? $segment['code'] ?? '')).']',
                'reference' => trim((string) ($segment['value'] ?? '')) !== ''
                    ? (string) $segment['value']
                    : (string) ($segment['empty_label'] ?? ''),
                default => '',
            };
        }

        return trim(implode('', $parts));
    }

    public function normalizeCode(string $code): string
    {
        $normalized = mb_strtolower(trim($code), 'UTF-8');
        $normalized = preg_replace('/[^a-z0-9_]+/u', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return $normalized;
    }
}
