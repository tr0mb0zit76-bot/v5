<?php

namespace App\Support;

use App\Models\User;

/**
 * Лимиты истории command bar: хранение в браузере, размер запроса и окно контекста LLM.
 */
final class CommandBarHistoryLimits
{
    /**
     * @return array{
     *     tier: string,
     *     storage: int,
     *     request: int,
     *     llm: int,
     *     storage_extended: int,
     *     request_extended: int,
     *     llm_extended: int,
     *     can_extend: bool
     * }
     */
    public static function profileForUser(?User $user): array
    {
        $tier = self::tierForUser($user);
        $tiers = config('ai.command_bar.history.tiers', []);
        $defaults = is_array($tiers['default'] ?? null) ? $tiers['default'] : [];
        $tierConfig = is_array($tiers[$tier] ?? null) ? $tiers[$tier] : $defaults;

        $storage = self::intLimit($tierConfig['storage'] ?? $defaults['storage'] ?? 40, 4, 500);
        $request = self::intLimit($tierConfig['request'] ?? $defaults['request'] ?? 20, 2, 250);
        $llm = self::intLimit($tierConfig['llm'] ?? $defaults['llm'] ?? 10, 2, 120);

        $storageExtended = self::intLimit(
            $tierConfig['storage_extended'] ?? max($storage, (int) round($storage * 2)),
            $storage,
            500,
        );
        $requestExtended = self::intLimit(
            $tierConfig['request_extended'] ?? max($request, (int) round($request * 2)),
            $request,
            250,
        );
        $llmExtended = self::intLimit(
            $tierConfig['llm_extended'] ?? max($llm, (int) round($llm * 2)),
            $llm,
            120,
        );

        return [
            'tier' => $tier,
            'storage' => $storage,
            'request' => $request,
            'llm' => $llm,
            'storage_extended' => $storageExtended,
            'request_extended' => $requestExtended,
            'llm_extended' => $llmExtended,
            'can_extend' => (bool) ($tierConfig['can_extend'] ?? $defaults['can_extend'] ?? true),
        ];
    }

    public static function requestMax(?User $user, bool $extended = false): int
    {
        $profile = self::profileForUser($user);

        return $extended ? $profile['request_extended'] : $profile['request'];
    }

    public static function llmMax(?User $user, bool $extended = false): int
    {
        $profile = self::profileForUser($user);

        return $extended ? $profile['llm_extended'] : $profile['llm'];
    }

    public static function storageMax(?User $user, bool $extended = false): int
    {
        $profile = self::profileForUser($user);

        return $extended ? $profile['storage_extended'] : $profile['storage'];
    }

    private static function tierForUser(?User $user): string
    {
        if ($user === null) {
            return 'default';
        }

        if ($user->isAdmin()) {
            return 'admin';
        }

        if ($user->isSupervisor()) {
            return 'supervisor';
        }

        return 'default';
    }

    private static function intLimit(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}
