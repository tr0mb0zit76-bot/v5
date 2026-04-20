<?php

namespace App\Support;

/**
 * Сборка порядка и набора кнопок нижней панели: роль по умолчанию и переопределение пользователя.
 */
final class MobileNavPreference
{
    private const MAX_KEYS = 6;

    /**
     * @param  list<string>  $candidateKeys
     * @param  list<string>|null  $userKeys
     * @param  list<string>|null  $roleDefaults
     * @return list<string>
     */
    public static function resolve(array $candidateKeys, ?array $userKeys, ?array $roleDefaults): array
    {
        $candidateSet = array_flip($candidateKeys);

        $pickOrdered = function (?array $preferred) use ($candidateSet): array {
            if (! is_array($preferred) || $preferred === []) {
                return [];
            }

            $seen = [];
            $picked = [];
            foreach ($preferred as $key) {
                if (! is_string($key) || $key === '') {
                    continue;
                }
                if (! isset($candidateSet[$key]) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $picked[] = $key;
                if (count($picked) >= self::MAX_KEYS) {
                    break;
                }
            }

            return $picked;
        };

        $fromUser = $pickOrdered($userKeys);
        if ($fromUser !== []) {
            return $fromUser;
        }

        $fromRole = $pickOrdered($roleDefaults);
        if ($fromRole !== []) {
            return $fromRole;
        }

        $fallback = [];
        foreach (MobileNavCatalog::ORDER as $key) {
            if (isset($candidateSet[$key])) {
                $fallback[] = $key;
            }
            if (count($fallback) >= self::MAX_KEYS) {
                break;
            }
        }

        return $fallback;
    }
}
