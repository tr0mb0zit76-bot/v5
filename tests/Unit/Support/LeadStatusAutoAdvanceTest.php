<?php

namespace Tests\Unit\Support;

use App\Support\LeadStatusAutoAdvance;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadStatusAutoAdvanceTest extends TestCase
{
    #[DataProvider('autoAdvanceCases')]
    public function test_resolves_forward_only_status(string $current, ?array $routePoints, ?array $cargoItems, mixed $targetPrice, string $expected): void
    {
        $this->assertSame(
            $expected,
            LeadStatusAutoAdvance::resolve($current, $routePoints, $cargoItems, $targetPrice),
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<int, array<string, mixed>>|null, 2: array<int, array<string, mixed>>|null, 3: mixed, 4: string}>
     */
    public static function autoAdvanceCases(): array
    {
        $route = [['type' => 'loading', 'address' => 'Самара', 'planned_date' => '2026-06-10']];
        $cargo = [['name' => 'Оборудование']];

        return [
            'new stays without data' => ['new', null, null, null, 'new'],
            'qualification to calculation' => ['qualification', $route, $cargo, null, 'calculation'],
            'calculation to proposal when price set' => ['calculation', $route, $cargo, 150000, 'proposal_ready'],
            'closed statuses untouched' => ['won', $route, $cargo, 150000, 'won'],
            'on hold untouched' => ['on_hold', $route, $cargo, 150000, 'on_hold'],
        ];
    }
}
