<?php

namespace App\Support;

use App\Models\User;

final class CrmFeatureCatalog
{
    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        $features = config('crm_features', []);

        if (! is_array($features)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($features),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(?User $user = null): array
    {
        $snapshot = [];

        foreach (self::keys() as $key) {
            $config = config("crm_features.{$key}");

            if (! is_array($config)) {
                continue;
            }

            $snapshot[$key] = [
                'label' => (string) ($config['label'] ?? $key),
                'enabled' => self::isEnabled($key, $user),
                'depends' => is_array($config['depends'] ?? null) ? $config['depends'] : [],
            ];
        }

        return $snapshot;
    }

    public static function isEnabled(string $key, ?User $user = null): bool
    {
        $config = config("crm_features.{$key}");

        if (! is_array($config) || ! ($config['enabled'] ?? false)) {
            return false;
        }

        foreach ($config['depends'] ?? [] as $dependency) {
            if (! is_string($dependency) || $dependency === '') {
                continue;
            }

            if (! self::isDependencyMet($dependency, $user)) {
                return false;
            }
        }

        return true;
    }

    private static function isDependencyMet(string $dependency, ?User $user): bool
    {
        if (in_array($dependency, RoleAccess::visibilityAreaKeys(), true)) {
            return $user !== null && RoleAccess::canAccessVisibilityArea($user, $dependency);
        }

        if (in_array($dependency, self::keys(), true)) {
            return self::isEnabled($dependency, $user);
        }

        return true;
    }
}
