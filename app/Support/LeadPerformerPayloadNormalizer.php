<?php

declare(strict_types=1);

namespace App\Support;

final class LeadPerformerPayloadNormalizer
{
    /**
     * @param  list<array<string, mixed>>|null  $performers
     * @return list<array<string, mixed>>
     */
    public static function normalizeList(?array $performers): array
    {
        if (! is_array($performers) || $performers === []) {
            return [self::blank('leg_1')];
        }

        $normalized = [];
        foreach ($performers as $index => $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $normalized[] = self::normalizeOne($performer, $index);
        }

        return $normalized !== [] ? $normalized : [self::blank('leg_1')];
    }

    /**
     * @param  array<string, mixed>  $performer
     * @return array<string, mixed>
     */
    public static function normalizeOne(array $performer, int $index = 0): array
    {
        $stage = self::normalizeStage(
            isset($performer['stage']) ? (string) $performer['stage'] : null,
            $index,
        );

        $contractorId = $performer['contractor_id'] ?? null;
        if ($contractorId !== null && $contractorId !== '') {
            $contractorId = (int) $contractorId;
        } else {
            $contractorId = null;
        }

        $estimatedCost = $performer['estimated_cost'] ?? null;
        if ($estimatedCost !== null && $estimatedCost !== '') {
            $estimatedCost = round((float) $estimatedCost, 2);
        } else {
            $estimatedCost = null;
        }

        return [
            'stage' => $stage,
            'contractor_id' => $contractorId,
            'contractor_name' => self::nullIfEmpty($performer['contractor_name'] ?? null),
            'estimated_cost' => $estimatedCost,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blank(string $stage = 'leg_1'): array
    {
        return [
            'stage' => self::normalizeStage($stage, 0),
            'contractor_id' => null,
            'contractor_name' => null,
            'estimated_cost' => null,
        ];
    }

    private static function normalizeStage(?string $stage, int $fallbackIndex): string
    {
        $value = trim((string) ($stage ?? ''));
        if ($value === '') {
            return 'leg_'.($fallbackIndex + 1);
        }

        if (preg_match('/^leg_(\d+)$/i', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return 'leg_'.$value;
        }

        return $value;
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
