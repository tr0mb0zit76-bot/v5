<?php

namespace App\Support;

use App\Models\BusinessProcessStage;

/**
 * Какие nudges включены на этапе бизнес-процесса.
 */
final class CommercialNudgeStageConfig
{
    /**
     * @return list<CommercialNudgeType>
     */
    public static function enabledTypes(BusinessProcessStage $stage): array
    {
        $raw = $stage->nudge_triggers;

        if (is_array($raw) && $raw !== []) {
            return array_values(array_filter(array_map(
                fn (mixed $value): ?CommercialNudgeType => CommercialNudgeType::tryFrom((string) $value),
                $raw,
            )));
        }

        $defaults = config('commercial_nudges.default_triggers', []);

        return array_values(array_filter(array_map(
            fn (mixed $value): ?CommercialNudgeType => CommercialNudgeType::tryFrom((string) $value),
            is_array($defaults) ? $defaults : [],
        )));
    }

    public static function isEnabled(BusinessProcessStage $stage, CommercialNudgeType $type): bool
    {
        foreach (self::enabledTypes($stage) as $enabled) {
            if ($enabled === $type) {
                return true;
            }
        }

        return false;
    }

    public static function noReplyDays(BusinessProcessStage $stage): int
    {
        if ($stage->no_reply_nudge_days !== null) {
            return max(1, (int) $stage->no_reply_nudge_days);
        }

        return max(1, (int) config('commercial_nudges.default_no_reply_days', 3));
    }

    public static function ledgerIdleDays(BusinessProcessStage $stage): ?int
    {
        if ($stage->ledger_idle_nudge_days === null) {
            return null;
        }

        $days = (int) $stage->ledger_idle_nudge_days;

        return $days > 0 ? $days : null;
    }
}
