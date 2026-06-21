<?php

namespace Tests\Unit;

use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Support\RoutePointActualMilestones;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoutePointActualMilestonesTest extends TestCase
{
    #[Test]
    public function unloading_milestone_requires_actual_date_on_last_unloading_point(): void
    {
        $milestones = RoutePointActualMilestones::fromLegsCollection(new Collection([
            $this->legWithPoints([
                ['type' => 'loading', 'actual_date' => '2026-05-10'],
                ['type' => 'unloading', 'actual_date' => '2026-05-15'],
                ['type' => 'unloading', 'actual_date' => null],
            ]),
        ]));

        $this->assertNotNull($milestones['actual_loading']);
        $this->assertNull($milestones['actual_unloading']);
    }

    #[Test]
    public function unloading_milestone_set_when_last_unloading_has_actual_date(): void
    {
        $milestones = RoutePointActualMilestones::fromLegsCollection(new Collection([
            $this->legWithPoints([
                ['type' => 'unloading', 'actual_date' => '2026-05-15'],
                ['type' => 'unloading', 'actual_date' => '2026-05-20'],
            ]),
        ]));

        $this->assertSame('2026-05-20', $milestones['actual_unloading']?->toDateString());
    }

    /**
     * @param  list<array{type: string, actual_date: ?string}>  $points
     */
    private function legWithPoints(array $points): OrderLeg
    {
        $leg = new OrderLeg;
        $leg->setRelation('routePoints', collect($points)->map(function (array $point, int $index): RoutePoint {
            $routePoint = new RoutePoint;
            $routePoint->type = $point['type'];
            $routePoint->sequence = $index + 1;
            $routePoint->actual_date = $point['actual_date'];

            return $routePoint;
        }));

        return $leg;
    }
}
