<?php

namespace Tests\Unit;

use App\Support\PerformerRouteActualDates;
use PHPUnit\Framework\TestCase;

class PerformerRouteActualDatesTest extends TestCase
{
    public function test_hydrates_performer_dates_from_route_points(): void
    {
        $performers = [
            ['stage' => 'leg_1', 'loading_actual' => null, 'unloading_actual' => null],
        ];

        $routePoints = [
            ['stage' => 'leg_1', 'type' => 'loading', 'sequence' => 1, 'actual_date' => '2026-05-01'],
            ['stage' => 'leg_1', 'type' => 'unloading', 'sequence' => 2, 'actual_date' => '2026-05-03'],
        ];

        $hydrated = PerformerRouteActualDates::hydratePerformersFromRoutePoints($performers, $routePoints);

        $this->assertSame('2026-05-01', $hydrated[0]['loading_actual']);
        $this->assertSame('2026-05-03', $hydrated[0]['unloading_actual']);
    }

    public function test_applies_performer_dates_to_first_loading_and_last_unloading_on_stage(): void
    {
        $performers = [
            [
                'stage' => 'leg_2',
                'loading_actual' => '2026-05-10',
                'unloading_actual' => '2026-05-12',
            ],
        ];

        $routePoints = [
            ['stage' => 'leg_2', 'type' => 'loading', 'sequence' => 1, 'actual_date' => null],
            ['stage' => 'leg_2', 'type' => 'loading', 'sequence' => 2, 'actual_date' => '2026-01-01'],
            ['stage' => 'leg_2', 'type' => 'unloading', 'sequence' => 3, 'actual_date' => null],
            ['stage' => 'leg_2', 'type' => 'unloading', 'sequence' => 4, 'actual_date' => '2026-01-02'],
        ];

        $synced = PerformerRouteActualDates::applyPerformersToRoutePoints($routePoints, $performers);

        $this->assertSame('2026-05-10', $synced[0]['actual_date']);
        $this->assertSame('2026-01-01', $synced[1]['actual_date']);
        $this->assertNull($synced[2]['actual_date']);
        $this->assertSame('2026-05-12', $synced[3]['actual_date']);
    }

    public function test_milestones_from_performers_use_first_loading_and_last_unloading(): void
    {
        $milestones = PerformerRouteActualDates::milestonesFromPerformers([
            ['stage' => 'leg_1', 'loading_actual' => '2026-05-01', 'unloading_actual' => null],
            ['stage' => 'leg_2', 'loading_actual' => null, 'unloading_actual' => '2026-05-20'],
        ]);

        $this->assertSame('2026-05-01', $milestones['actual_loading']?->toDateString());
        $this->assertSame('2026-05-20', $milestones['actual_unloading']?->toDateString());
    }

    public function test_split_performer_dates_hydrate_to_each_slot_and_aggregate_to_route_points(): void
    {
        $performers = [
            [
                'stage' => 'leg_1',
                'carrier_mode' => 'split',
                'loading_actual' => '2026-05-01',
                'unloading_actual' => '2026-05-05',
                'split_carriers' => [
                    ['slot' => 1, 'loading_actual' => null, 'unloading_actual' => null],
                    ['slot' => 2, 'loading_actual' => null, 'unloading_actual' => null],
                ],
            ],
        ];

        $routePoints = [
            ['stage' => 'leg_1', 'type' => 'loading', 'sequence' => 1, 'actual_date' => null],
            ['stage' => 'leg_1', 'type' => 'unloading', 'sequence' => 2, 'actual_date' => null],
        ];

        $hydrated = PerformerRouteActualDates::hydratePerformersFromRoutePoints($performers, $routePoints);

        $this->assertNull($hydrated[0]['loading_actual']);
        $this->assertSame('2026-05-01', $hydrated[0]['split_carriers'][0]['loading_actual']);
        $this->assertSame('2026-05-05', $hydrated[0]['split_carriers'][1]['unloading_actual']);

        $hydrated[0]['split_carriers'][0]['loading_actual'] = '2026-05-02';
        $hydrated[0]['split_carriers'][1]['loading_actual'] = null;
        $hydrated[0]['split_carriers'][1]['unloading_actual'] = '2026-05-08';

        $synced = PerformerRouteActualDates::applyPerformersToRoutePoints($routePoints, $hydrated);

        $this->assertSame('2026-05-02', $synced[0]['actual_date']);
        $this->assertSame('2026-05-08', $synced[1]['actual_date']);
    }
}
