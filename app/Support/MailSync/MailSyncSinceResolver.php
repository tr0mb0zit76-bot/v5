<?php

declare(strict_types=1);

namespace App\Support\MailSync;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class MailSyncSinceResolver
{
    public static function resolve(
        ?CarbonInterface $lastSyncAt,
        ?int $daysOverride,
        ?int $initialDays = null,
        ?int $overlapHours = null,
    ): CarbonImmutable {
        if ($daysOverride !== null) {
            return CarbonImmutable::now()->subDays(max(1, $daysOverride));
        }

        $initialDays = max(1, $initialDays ?? (int) config('mail_sync.initial_sync_days', 30));
        $overlapHours = max(0, $overlapHours ?? (int) config('mail_sync.incremental_overlap_hours', 24));

        if ($lastSyncAt !== null) {
            return CarbonImmutable::parse($lastSyncAt)->subHours($overlapHours);
        }

        return CarbonImmutable::now()->subDays($initialDays);
    }
}
