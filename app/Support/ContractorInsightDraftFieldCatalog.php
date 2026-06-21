<?php

namespace App\Support;

final class ContractorInsightDraftFieldCatalog
{
    /** @var list<string> */
    public const WHITELIST = [
        'success_criteria',
        'preferred_channel',
        'price_sensitivity',
        'typical_objections',
        'internal_notes',
    ];

    public static function isAllowed(string $fieldKey): bool
    {
        return in_array($fieldKey, self::WHITELIST, true);
    }

    public static function label(string $fieldKey): string
    {
        return match ($fieldKey) {
            'success_criteria' => 'Критерии успеха',
            'preferred_channel' => 'Предпочитаемый канал',
            'price_sensitivity' => 'Чувствительность к цене',
            'typical_objections' => 'Типичные возражения',
            'internal_notes' => 'Внутренняя памятка',
            default => $fieldKey,
        };
    }

    /**
     * @return mixed|null
     */
    public static function normalizeProposedValue(string $fieldKey, mixed $value): mixed
    {
        if (! self::isAllowed($fieldKey)) {
            return null;
        }

        if ($fieldKey === 'typical_objections') {
            if (! is_array($value)) {
                return null;
            }

            $tags = array_values(array_filter(array_map(
                fn (mixed $tag): string => trim((string) $tag),
                $value,
            ), fn (string $tag): bool => $tag !== ''));

            return $tags === [] ? null : $tags;
        }

        if ($fieldKey === 'preferred_channel') {
            $normalized = is_string($value) ? trim($value) : '';

            return in_array($normalized, ContractorPortraitDictionary::preferredChannels(), true)
                && $normalized !== ContractorPortraitDictionary::UNKNOWN
                ? $normalized
                : null;
        }

        if ($fieldKey === 'price_sensitivity') {
            $normalized = is_string($value) ? trim($value) : '';

            return in_array($normalized, ContractorPortraitDictionary::priceSensitivities(), true)
                && $normalized !== ContractorPortraitDictionary::UNKNOWN
                ? $normalized
                : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }

    /**
     * @return array<string, mixed>
     */
    public static function displayValue(string $fieldKey, mixed $proposedValue): array
    {
        if ($fieldKey === 'typical_objections' && is_array($proposedValue)) {
            return [
                'text' => implode(', ', $proposedValue),
                'tags' => $proposedValue,
            ];
        }

        if ($fieldKey === 'preferred_channel' || $fieldKey === 'price_sensitivity') {
            $value = is_string($proposedValue) ? $proposedValue : '';

            return [
                'text' => ContractorPortraitDictionary::label($fieldKey, $value),
                'value' => $value,
            ];
        }

        return [
            'text' => is_string($proposedValue) ? $proposedValue : json_encode($proposedValue, JSON_UNESCAPED_UNICODE),
        ];
    }
}
