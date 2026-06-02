<?php

namespace App\Support;

use App\Enums\LeadCloseOutcomeFlag;

final class LeadCloseOutcomeFlagCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function lostOptions(): array
    {
        return array_map(
            fn (LeadCloseOutcomeFlag $flag): array => ['value' => $flag->value, 'label' => $flag->label()],
            LeadCloseOutcomeFlag::forLost(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function wonOptions(): array
    {
        return array_map(
            fn (LeadCloseOutcomeFlag $flag): array => ['value' => $flag->value, 'label' => $flag->label()],
            LeadCloseOutcomeFlag::forWon(),
        );
    }

    public static function label(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return LeadCloseOutcomeFlag::tryFrom($value)?->label() ?? $value;
    }
}
