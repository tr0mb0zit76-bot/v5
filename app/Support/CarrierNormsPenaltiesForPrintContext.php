<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Выбор строки carrier_norms_by_leg для подстановки в печать по этапу плеча.
 */
final class CarrierNormsPenaltiesForPrintContext
{
    /**
     * @param  list<mixed>  $carrierRows
     * @return array<string, mixed>
     */
    public static function resolveRow(array $carrierRows, ?string $targetStage): array
    {
        if ($carrierRows === []) {
            return [];
        }

        if ($targetStage !== null && $targetStage !== '') {
            $normalizedTarget = self::normalizeStageIdentifier($targetStage);

            foreach ($carrierRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowStage = isset($row['stage']) && is_string($row['stage']) && trim($row['stage']) !== ''
                    ? self::normalizeStageIdentifier($row['stage'])
                    : null;

                if ($rowStage === $normalizedTarget) {
                    return $row;
                }
            }
        }

        $first = $carrierRows[0];

        return is_array($first) ? $first : [];
    }

    private static function normalizeStageIdentifier(string $stage): string
    {
        $value = trim($stage);

        if ($value === '') {
            return 'leg_1';
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^плечо\s*(\d+)$/ui', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        return $value;
    }
}
