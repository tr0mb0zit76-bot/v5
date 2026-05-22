<?php

namespace App\Support;

final class CrmAppearance
{
    public const BUTTON_RADIUS_SHARP = 'sharp';

    public const BUTTON_RADIUS_ROUNDED = 'rounded';

    public const PRIMARY_ACCENT_EMERALD = 'emerald';

    public const PRIMARY_ACCENT_SKY = 'sky';

    public const TAB_STYLE_FILLED = 'filled';

    public const TAB_STYLE_UNDERLINE = 'underline';

    public const WORKSPACE_SKIN_CLASSIC = 'classic';

    public const WORKSPACE_SKIN_SKY = 'sky';

    /**
     * @return array{button_radius: string, primary_accent: string, tab_style: string, workspace_skin: string, ag_grid_density: string}
     */
    public static function defaults(): array
    {
        return [
            'button_radius' => self::BUTTON_RADIUS_ROUNDED,
            'primary_accent' => self::PRIMARY_ACCENT_SKY,
            'tab_style' => self::TAB_STYLE_FILLED,
            'workspace_skin' => self::WORKSPACE_SKIN_SKY,
            'ag_grid_density' => 'normal',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function buttonRadiusOptions(): array
    {
        return [
            ['value' => self::BUTTON_RADIUS_ROUNDED, 'label' => 'Скруглённые'],
            ['value' => self::BUTTON_RADIUS_SHARP, 'label' => 'Прямые углы'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function primaryAccentOptions(): array
    {
        return [
            ['value' => self::PRIMARY_ACCENT_EMERALD, 'label' => 'Зелёные'],
            ['value' => self::PRIMARY_ACCENT_SKY, 'label' => 'Голубые'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function tabStyleOptions(): array
    {
        return [
            ['value' => self::TAB_STYLE_FILLED, 'label' => 'Заливка'],
            ['value' => self::TAB_STYLE_UNDERLINE, 'label' => 'Подчёркивание'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function workspaceSkinOptions(): array
    {
        return [
            ['value' => self::WORKSPACE_SKIN_CLASSIC, 'label' => 'Классический'],
            ['value' => self::WORKSPACE_SKIN_SKY, 'label' => 'Sky (как «Сколько влезет»)'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{button_radius: string, primary_accent: string, tab_style: string, workspace_skin: string, ag_grid_density: string}
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        $stored = is_array($stored) ? $stored : [];

        return [
            'button_radius' => self::normalizeButtonRadius($stored['button_radius'] ?? null) ?? $defaults['button_radius'],
            'primary_accent' => self::normalizePrimaryAccent($stored['primary_accent'] ?? null) ?? $defaults['primary_accent'],
            'tab_style' => self::normalizeTabStyle($stored['tab_style'] ?? null) ?? $defaults['tab_style'],
            'workspace_skin' => self::normalizeWorkspaceSkin($stored['workspace_skin'] ?? null) ?? $defaults['workspace_skin'],
            'ag_grid_density' => self::normalizeAgGridDensity($stored['ag_grid_density'] ?? null) ?? $defaults['ag_grid_density'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function mergeValidated(array $validated, ?array $existing): array
    {
        return array_merge(self::resolve($existing), array_filter([
            'button_radius' => isset($validated['button_radius'])
                ? self::normalizeButtonRadius($validated['button_radius'])
                : null,
            'primary_accent' => isset($validated['primary_accent'])
                ? self::normalizePrimaryAccent($validated['primary_accent'])
                : null,
            'tab_style' => isset($validated['tab_style'])
                ? self::normalizeTabStyle($validated['tab_style'])
                : null,
            'workspace_skin' => isset($validated['workspace_skin'])
                ? self::normalizeWorkspaceSkin($validated['workspace_skin'])
                : null,
            'ag_grid_density' => isset($validated['ag_grid_density'])
                ? self::normalizeAgGridDensity($validated['ag_grid_density'])
                : null,
        ], fn (?string $value): bool => $value !== null));
    }

    private static function normalizeButtonRadius(mixed $value): ?string
    {
        return in_array($value, [self::BUTTON_RADIUS_SHARP, self::BUTTON_RADIUS_ROUNDED], true)
            ? (string) $value
            : null;
    }

    private static function normalizePrimaryAccent(mixed $value): ?string
    {
        return in_array($value, [self::PRIMARY_ACCENT_EMERALD, self::PRIMARY_ACCENT_SKY], true)
            ? (string) $value
            : null;
    }

    private static function normalizeTabStyle(mixed $value): ?string
    {
        return in_array($value, [self::TAB_STYLE_FILLED, self::TAB_STYLE_UNDERLINE], true)
            ? (string) $value
            : null;
    }

    private static function normalizeWorkspaceSkin(mixed $value): ?string
    {
        return in_array($value, [self::WORKSPACE_SKIN_CLASSIC, self::WORKSPACE_SKIN_SKY], true)
            ? (string) $value
            : null;
    }

    private static function normalizeAgGridDensity(mixed $value): ?string
    {
        return in_array($value, ['compact', 'normal', 'comfortable'], true)
            ? (string) $value
            : null;
    }
}
