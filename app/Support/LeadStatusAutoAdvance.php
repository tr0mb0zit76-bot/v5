<?php

namespace App\Support;

final class LeadStatusAutoAdvance
{
    /**
     * @var array<string, int>
     */
    private const ORDER = [
        'new' => 0,
        'qualification' => 1,
        'calculation' => 2,
        'proposal_ready' => 3,
        'proposal_sent' => 4,
        'negotiation' => 5,
        'on_hold' => 6,
        'won' => 7,
        'lost' => 7,
    ];

    /**
     * Продвигает статус только вперёд по воронке; закрытые и отложенные не трогаем.
     *
     * @param  array<int, array<string, mixed>>|null  $routePoints
     * @param  array<int, array<string, mixed>>|null  $cargoItems
     */
    public static function resolve(string $currentStatus, ?array $routePoints, ?array $cargoItems, mixed $targetPrice = null): string
    {
        if (LeadStatus::isClosed($currentStatus) || $currentStatus === 'on_hold') {
            return $currentStatus;
        }

        $suggested = $currentStatus;

        if (self::hasMeaningfulRoute($routePoints) && self::hasMeaningfulCargo($cargoItems)) {
            $suggested = self::maxStatus($suggested, 'calculation');
        }

        if ($targetPrice !== null && $targetPrice !== '') {
            $suggested = self::maxStatus($suggested, 'proposal_ready');
        }

        return $suggested;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $routePoints
     */
    public static function hasMeaningfulRoute(?array $routePoints): bool
    {
        if ($routePoints === null || $routePoints === []) {
            return false;
        }

        foreach ($routePoints as $point) {
            if (! is_array($point)) {
                continue;
            }

            if (filled($point['address'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $cargoItems
     */
    public static function hasMeaningfulCargo(?array $cargoItems): bool
    {
        if ($cargoItems === null || $cargoItems === []) {
            return false;
        }

        foreach ($cargoItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (filled($item['name'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private static function maxStatus(string $current, string $candidate): string
    {
        $currentOrder = self::ORDER[$current] ?? 0;
        $candidateOrder = self::ORDER[$candidate] ?? 0;

        if ($candidateOrder <= $currentOrder) {
            return $current;
        }

        return $candidate;
    }
}
