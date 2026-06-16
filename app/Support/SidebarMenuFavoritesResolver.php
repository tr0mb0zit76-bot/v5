<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SidebarMenuFavoritesResolver
{
    /**
     * @return array{
     *     keys: list<string>,
     *     items: list<array{key: string, label: string, href: string}>,
     *     candidate_keys: list<string>,
     *     max: int,
     *     labels: array<string, string>
     * }|null
     */
    public static function forInertiaUser(?User $user): ?array
    {
        if ($user === null || ! Schema::hasColumn('users', 'ui_preferences')) {
            return null;
        }

        $candidates = SidebarMenuCatalog::candidateKeysForUser($user);
        $savedKeys = self::savedKeys($user);
        $resolvedKeys = self::resolveKeys($candidates, $savedKeys);
        $routes = SidebarMenuCatalog::routes();
        $labels = SidebarMenuCatalog::labels();

        $items = [];
        foreach ($resolvedKeys as $key) {
            if (! isset($routes[$key])) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'href' => $routes[$key],
            ];
        }

        return [
            'keys' => $resolvedKeys,
            'items' => $items,
            'candidate_keys' => $candidates,
            'max' => SidebarMenuCatalog::maxFavorites(),
            'labels' => $labels,
        ];
    }

    /**
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    public static function sanitizeUserSelection(User $user, array $requestedKeys): array
    {
        $candidates = SidebarMenuCatalog::candidateKeysForUser($user);
        $allowed = array_flip($candidates);
        $picked = [];

        foreach ($requestedKeys as $key) {
            if (! is_string($key) || $key === '' || ! isset($allowed[$key])) {
                continue;
            }

            if (in_array($key, $picked, true)) {
                continue;
            }

            $picked[] = $key;

            if (count($picked) >= SidebarMenuCatalog::maxFavorites()) {
                break;
            }
        }

        return $picked;
    }

    /**
     * @param  list<string>  $candidates
     * @param  list<string>|null  $savedKeys
     * @return list<string>
     */
    private static function resolveKeys(array $candidates, ?array $savedKeys): array
    {
        if (! is_array($savedKeys) || $savedKeys === []) {
            return [];
        }

        $allowed = array_flip($candidates);
        $resolved = [];

        foreach ($savedKeys as $key) {
            if (! is_string($key) || $key === '' || ! isset($allowed[$key])) {
                continue;
            }

            if (in_array($key, $resolved, true)) {
                continue;
            }

            $resolved[] = $key;

            if (count($resolved) >= SidebarMenuCatalog::maxFavorites()) {
                break;
            }
        }

        return $resolved;
    }

    /**
     * @return list<string>|null
     */
    private static function savedKeys(User $user): ?array
    {
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : null;
        if ($preferences === null) {
            return null;
        }

        $keys = $preferences['sidebar_favorite_keys'] ?? null;
        if (! is_array($keys) || $keys === []) {
            return null;
        }

        $normalized = [];
        foreach ($keys as $key) {
            if (is_string($key) && $key !== '') {
                $normalized[] = $key;
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}
