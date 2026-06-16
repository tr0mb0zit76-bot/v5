<?php

declare(strict_types=1);

namespace App\Support;

final class CarrierPortalSubmission
{
    /**
     * @param  array<string, mixed>|null  $submission
     */
    public static function isUsable(?array $submission): bool
    {
        if (! is_array($submission)) {
            return false;
        }

        return filled($submission['driver_full_name'] ?? null)
            || filled($submission['tractor_plate'] ?? null)
            || filled($submission['trailer_plate'] ?? null);
    }
}
