<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LeadRoutePoint;

final class LeadRoutePointPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $routePoint
     * @return array<string, mixed>
     */
    public static function toDatabase(array $routePoint): array
    {
        $normalizedData = is_array($routePoint['normalized_data'] ?? null)
            ? $routePoint['normalized_data']
            : [];

        foreach ([
            'planned_time_from',
            'planned_time_to',
            'sender_name',
            'sender_contact',
            'sender_phone',
            'recipient_name',
            'recipient_contact',
            'recipient_phone',
        ] as $key) {
            if (! array_key_exists($key, $routePoint)) {
                continue;
            }

            $value = $routePoint[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $normalizedData[$key] = $value;
        }

        $address = trim((string) ($routePoint['address'] ?? ''));
        $normalizedData = RoutePointNormalizedData::prepareForStorage($normalizedData, $address !== '' ? $address : null);

        return [
            'type' => (string) ($routePoint['type'] ?? 'loading'),
            'stage' => LeadPerformerPayloadNormalizer::normalizeOne([
                'stage' => $routePoint['stage'] ?? 'leg_1',
            ])['stage'],
            'sequence' => isset($routePoint['sequence']) ? (int) $routePoint['sequence'] : null,
            'address' => $address !== '' ? $address : null,
            'normalized_data' => $normalizedData,
            'planned_date' => self::nullIfEmpty($routePoint['planned_date'] ?? null),
            'contact_person' => self::nullIfEmpty($routePoint['contact_person'] ?? null),
            'contact_phone' => self::nullIfEmpty($routePoint['contact_phone'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toFrontend(LeadRoutePoint $point): array
    {
        $normalized = $point->normalized_data ?? [];
        if (! is_array($normalized)) {
            $normalized = [];
        }

        return [
            'id' => $point->id,
            'type' => $point->type,
            'stage' => (string) ($point->stage ?? 'leg_1'),
            'sequence' => $point->sequence,
            'address' => $point->address ?? '',
            'normalized_data' => $normalized,
            'planned_date' => optional($point->planned_date)->toDateString() ?? '',
            'planned_time_from' => (string) ($normalized['planned_time_from'] ?? ''),
            'planned_time_to' => (string) ($normalized['planned_time_to'] ?? ''),
            'contact_person' => $point->contact_person ?? '',
            'contact_phone' => $point->contact_phone ?? '',
            'sender_name' => (string) ($normalized['sender_name'] ?? ''),
            'sender_contact' => (string) ($normalized['sender_contact'] ?? ''),
            'sender_phone' => (string) ($normalized['sender_phone'] ?? ''),
            'recipient_name' => (string) ($normalized['recipient_name'] ?? ''),
            'recipient_contact' => (string) ($normalized['recipient_contact'] ?? ''),
            'recipient_phone' => (string) ($normalized['recipient_phone'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $routePoint
     */
    public static function isMeaningful(array $routePoint): bool
    {
        $payload = self::toDatabase($routePoint);

        if ($payload['address'] !== null) {
            return true;
        }

        if ($payload['planned_date'] !== null) {
            return true;
        }

        if ($payload['contact_person'] !== null || $payload['contact_phone'] !== null) {
            return true;
        }

        $normalized = $payload['normalized_data'];
        if (! is_array($normalized)) {
            return false;
        }

        foreach ($normalized as $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }

        return false;
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
