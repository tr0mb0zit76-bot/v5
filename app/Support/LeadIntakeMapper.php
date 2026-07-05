<?php

namespace App\Support;

final class LeadIntakeMapper
{
    /**
     * @param  array<string, mixed>  $extracted  OrderIntakeSchema LLM shape
     * @return array{
     *     parsed: array<string, mixed>,
     *     lead_attributes: array<string, mixed>,
     *     metadata_intake: array<string, mixed>
     * }
     */
    public static function fromExtracted(array $extracted, string $rawMessage, string $parser): array
    {
        $parsed = self::parsedFromExtracted($extracted);

        return [
            'parsed' => array_merge($parsed, [
                'parser' => $parser,
                'confidence' => isset($extracted['confidence']) ? (float) $extracted['confidence'] : null,
            ]),
            'lead_attributes' => [
                'title' => self::title($parsed),
                'description' => self::description($rawMessage, $parsed, $parser),
                'loading_location' => $parsed['loading_location'],
                'unloading_location' => $parsed['unloading_location'],
                'planned_shipping_date' => $parsed['planned_shipping_date'],
            ],
            'metadata_intake' => [
                'raw_text' => $rawMessage,
                'contact_phone' => $parsed['phone'],
                'contact_name' => $parsed['contact_name'],
                'company_name' => $parsed['company_name'],
                'cargo' => $parsed['cargo'],
                'parser' => $parser,
                'parsed_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array{loading_location: string|null, unloading_location: string|null, cargo: string|null, phone: string|null, contact_name: string|null, company_name: string|null}  $heuristic
     * @return array{
     *     parsed: array<string, mixed>,
     *     lead_attributes: array<string, mixed>,
     *     metadata_intake: array<string, mixed>
     * }
     */
    public static function fromHeuristic(array $heuristic, string $rawMessage): array
    {
        return self::fromExtracted(self::heuristicToExtractedShape($heuristic), $rawMessage, 'heuristic');
    }

    /**
     * @param  array{loading_location: string|null, unloading_location: string|null, cargo: string|null, phone: string|null, contact_name: string|null, company_name: string|null}  $heuristic
     * @return array<string, mixed>
     */
    public static function heuristicToExtractedShape(array $heuristic): array
    {
        return [
            'customer' => [
                'name' => $heuristic['company_name'],
                'contact_name' => $heuristic['contact_name'],
                'contact_phone' => $heuristic['phone'],
            ],
            'route' => [
                'loading' => ['address' => $heuristic['loading_location']],
                'unloading' => ['address' => $heuristic['unloading_location']],
            ],
            'cargo' => [
                'name' => $heuristic['cargo'],
            ],
            'confidence' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array<string, mixed>
     */
    public static function parsedFromExtracted(array $extracted): array
    {
        $customer = is_array($extracted['customer'] ?? null) ? $extracted['customer'] : [];
        $route = is_array($extracted['route'] ?? null) ? $extracted['route'] : [];
        $loading = is_array($route['loading'] ?? null) ? $route['loading'] : [];
        $unloading = is_array($route['unloading'] ?? null) ? $route['unloading'] : [];
        $cargo = is_array($extracted['cargo'] ?? null) ? $extracted['cargo'] : [];

        $cargoText = self::nullableString($cargo['name'] ?? null)
            ?? self::nullableString($cargo['description'] ?? null);

        return [
            'loading_location' => self::nullableString($loading['address'] ?? null),
            'unloading_location' => self::nullableString($unloading['address'] ?? null),
            'cargo' => $cargoText,
            'phone' => self::nullableString($customer['contact_phone'] ?? null),
            'contact_name' => self::nullableString($customer['contact_name'] ?? null),
            'company_name' => self::nullableString($customer['name'] ?? null),
            'planned_shipping_date' => OrderAgentLexicon::normalizeDateValue($loading['planned_date'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function title(array $parsed): string
    {
        $loading = $parsed['loading_location'] ?: 'откуда не указано';
        $unloading = $parsed['unloading_location'] ?: 'куда не указано';

        return sprintf('Заявка из сообщения: %s → %s', $loading, $unloading);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function description(string $message, array $parsed, string $parser): string
    {
        $parserLabel = $parser === 'llm' ? 'AI (order intake)' : 'эвристики';

        $lines = [
            'Заявка создана из вставленного сообщения Traklo.',
            'Парсер: '.$parserLabel.'.',
            'Маршрут: '.($parsed['loading_location'] ?: 'не распознан').' → '.($parsed['unloading_location'] ?: 'не распознана'),
            'Груз: '.($parsed['cargo'] ?: 'не распознан'),
            'Телефон: '.($parsed['phone'] ?: 'не распознан'),
            '',
            'Исходный текст:',
            $message,
        ];

        return implode("\n", $lines);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
