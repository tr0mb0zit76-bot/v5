<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

final class AiAgentCatalog
{
    /**
     * @return list<array{slug: string, display_name: string, tagline: string}>
     */
    public static function optionsForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $options = [];

        foreach (self::personasConfig() as $slug => $persona) {
            if (! self::userCanUsePersona($user, $persona)) {
                continue;
            }

            $options[] = [
                'slug' => $slug,
                'display_name' => (string) ($persona['display_name'] ?? $slug),
                'tagline' => (string) ($persona['tagline'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * @return array{display_name: string, tagline: string, prompt_lead: string}|null
     */
    public static function resolveForUser(User $user, ?string $slug): ?array
    {
        $normalizedSlug = self::normalizeSlug($slug) ?? self::defaultSlug();
        $persona = self::personasConfig()[$normalizedSlug] ?? null;

        if (! is_array($persona) || ! self::userCanUsePersona($user, $persona)) {
            $normalizedSlug = self::defaultSlug();
            $persona = self::personasConfig()[$normalizedSlug] ?? null;
        }

        if (! is_array($persona)) {
            return null;
        }

        return [
            'slug' => $normalizedSlug,
            'display_name' => (string) ($persona['display_name'] ?? $normalizedSlug),
            'tagline' => (string) ($persona['tagline'] ?? ''),
            'prompt_lead' => (string) ($persona['prompt_lead'] ?? ''),
        ];
    }

    public static function defaultSlug(): string
    {
        $slug = (string) config('ai_agents.default_slug', 'jarvis');

        return self::normalizeSlug($slug) ?? 'jarvis';
    }

    private static function normalizeSlug(?string $slug): ?string
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $normalized = strtolower(trim($slug));

        return preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $normalized) === 1
            ? $normalized
            : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function personasConfig(): array
    {
        /** @var array<string, array<string, mixed>> $personas */
        $personas = config('ai_agents.personas', []);

        return $personas;
    }

    /**
     * @param  array<string, mixed>  $persona
     */
    private static function userCanUsePersona(User $user, array $persona): bool
    {
        $visibility = (string) ($persona['visibility'] ?? 'any_authenticated');

        if ($visibility === 'any_authenticated') {
            return true;
        }

        if ($visibility === 'visibility_any') {
            /** @var list<string> $areas */
            $areas = Arr::wrap($persona['visibility_areas'] ?? []);

            if ($areas !== [] && RoleAccess::canAccessAnyVisibilityArea($user, $areas)) {
                return true;
            }

            return RoleAccess::canAccessSettingsSystem($user);
        }

        if ($visibility === 'management_accounting') {
            return RoleAccess::canAccessManagementAccounting($user);
        }

        if ($visibility === 'head_of_sales') {
            return RoleAccess::canViewHeadOfSalesInsights($user);
        }

        return true;
    }
}
