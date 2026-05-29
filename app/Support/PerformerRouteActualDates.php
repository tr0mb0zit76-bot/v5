<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Generator;

/**
 * Фактические даты погрузки/выгрузки по исполнителю (слоту) с агрегацией в route_points плеча.
 */
final class PerformerRouteActualDates
{
    public static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = is_string($value) ? trim($value) : trim((string) $value);
        if ($string === '') {
            return null;
        }

        return substr($string, 0, 10);
    }

    public static function normalizeStageKey(?string $stage): string
    {
        $value = trim((string) $stage);

        if ($value === '') {
            return 'leg_1';
        }

        if (preg_match('/^Плечо\s+(\d+)$/u', $value, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        return $value;
    }

    public static function stagesMatch(?string $left, ?string $right): bool
    {
        return self::normalizeStageKey($left) === self::normalizeStageKey($right);
    }

    public static function isSplitPerformer(array $performer): bool
    {
        return ($performer['carrier_mode'] ?? 'single') === 'split'
            && is_array($performer['split_carriers'] ?? null)
            && $performer['split_carriers'] !== [];
    }

    /**
     * @return Generator<int, array{loading: ?string, unloading: ?string}>
     */
    public static function executorDateRows(array $performer): Generator
    {
        if (self::isSplitPerformer($performer)) {
            foreach ($performer['split_carriers'] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                yield [
                    'loading' => self::normalizeDate($slot['loading_actual'] ?? null),
                    'unloading' => self::normalizeDate($slot['unloading_actual'] ?? null),
                ];
            }

            return;
        }

        yield [
            'loading' => self::normalizeDate($performer['loading_actual'] ?? null),
            'unloading' => self::normalizeDate($performer['unloading_actual'] ?? null),
        ];
    }

    public static function performerHasLoadingActual(array $performer): bool
    {
        foreach (self::executorDateRows($performer) as $row) {
            if ($row['loading'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @param  list<array<string, mixed>>  $routePoints
     * @return list<array<string, mixed>>
     */
    public static function hydratePerformersFromRoutePoints(array $performers, array $routePoints): array
    {
        if ($performers === []) {
            return [];
        }

        return collect($performers)
            ->map(function (array $performer) use ($routePoints): array {
                $stage = (string) ($performer['stage'] ?? '');
                $routeLoading = self::firstRoutePointActualDate($routePoints, $stage, 'loading');
                $routeUnloading = self::lastRoutePointActualDate($routePoints, $stage, 'unloading');
                $legacyLoading = self::normalizeDate($performer['loading_actual'] ?? null);
                $legacyUnloading = self::normalizeDate($performer['unloading_actual'] ?? null);

                if (self::isSplitPerformer($performer)) {
                    $performer['loading_actual'] = null;
                    $performer['unloading_actual'] = null;
                    $performer['split_carriers'] = collect($performer['split_carriers'])
                        ->map(function (mixed $slot) use ($legacyLoading, $legacyUnloading, $routeLoading, $routeUnloading): array {
                            if (! is_array($slot)) {
                                return [];
                            }

                            $loading = self::normalizeDate($slot['loading_actual'] ?? null)
                                ?? $legacyLoading
                                ?? $routeLoading;
                            $unloading = self::normalizeDate($slot['unloading_actual'] ?? null)
                                ?? $legacyUnloading
                                ?? $routeUnloading;

                            $slot['loading_actual'] = $loading;
                            $slot['unloading_actual'] = $unloading;

                            return $slot;
                        })
                        ->filter(fn (array $slot): bool => $slot !== [])
                        ->values()
                        ->all();

                    return $performer;
                }

                $loading = $legacyLoading ?? $routeLoading;
                $unloading = $legacyUnloading ?? $routeUnloading;
                $performer['loading_actual'] = $loading;
                $performer['unloading_actual'] = $unloading;

                return $performer;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $routePoints
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    public static function applyPerformersToRoutePoints(array $routePoints, array $performers): array
    {
        if ($routePoints === [] || $performers === []) {
            return $routePoints;
        }

        $points = collect($routePoints)->values()->all();

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = (string) ($performer['stage'] ?? '');
            $loadings = [];
            $unloadings = [];

            foreach (self::executorDateRows($performer) as $row) {
                if ($row['loading'] !== null) {
                    $loadings[] = $row['loading'];
                }

                if ($row['unloading'] !== null) {
                    $unloadings[] = $row['unloading'];
                }
            }

            $loading = $loadings === [] ? null : min($loadings);
            $unloading = $unloadings === [] ? null : max($unloadings);

            if ($loading !== null) {
                $index = self::firstRoutePointIndex($points, $stage, 'loading');
                if ($index !== null) {
                    $points[$index]['actual_date'] = $loading;
                }
            }

            if ($unloading !== null) {
                $index = self::lastRoutePointIndex($points, $stage, 'unloading');
                if ($index !== null) {
                    $points[$index]['actual_date'] = $unloading;
                }
            }
        }

        return $points;
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return array{actual_loading: ?CarbonInterface, actual_unloading: ?CarbonInterface}
     */
    public static function milestonesFromPerformers(array $performers): array
    {
        $firstLoading = null;
        $lastUnloading = null;

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            foreach (self::executorDateRows($performer) as $row) {
                if ($row['loading'] !== null) {
                    $parsed = Carbon::parse($row['loading']);
                    if ($firstLoading === null || $parsed->lt($firstLoading)) {
                        $firstLoading = $parsed;
                    }
                }

                if ($row['unloading'] !== null) {
                    $parsed = Carbon::parse($row['unloading']);
                    if ($lastUnloading === null || $parsed->gt($lastUnloading)) {
                        $lastUnloading = $parsed;
                    }
                }
            }
        }

        return [
            'actual_loading' => $firstLoading,
            'actual_unloading' => $lastUnloading,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $routePoints
     */
    public static function firstRoutePointActualDate(array $routePoints, string $stage, string $type): ?string
    {
        $index = self::firstRoutePointIndex($routePoints, $stage, $type);
        if ($index === null) {
            return null;
        }

        return self::normalizeDate($routePoints[$index]['actual_date'] ?? null);
    }

    /**
     * @param  list<array<string, mixed>>  $routePoints
     */
    public static function lastRoutePointActualDate(array $routePoints, string $stage, string $type): ?string
    {
        $index = self::lastRoutePointIndex($routePoints, $stage, $type);
        if ($index === null) {
            return null;
        }

        return self::normalizeDate($routePoints[$index]['actual_date'] ?? null);
    }

    /**
     * @param  list<array<string, mixed>>  $routePoints
     */
    private static function firstRoutePointIndex(array $routePoints, string $stage, string $type): ?int
    {
        foreach ($routePoints as $index => $point) {
            if (! is_array($point)) {
                continue;
            }

            if ((string) ($point['type'] ?? '') !== $type) {
                continue;
            }

            if (! self::stagesMatch((string) ($point['stage'] ?? ''), $stage)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $routePoints
     */
    private static function lastRoutePointIndex(array $routePoints, string $stage, string $type): ?int
    {
        $last = null;

        foreach ($routePoints as $index => $point) {
            if (! is_array($point)) {
                continue;
            }

            if ((string) ($point['type'] ?? '') !== $type) {
                continue;
            }

            if (! self::stagesMatch((string) ($point['stage'] ?? ''), $stage)) {
                continue;
            }

            $last = $index;
        }

        return $last;
    }
}
