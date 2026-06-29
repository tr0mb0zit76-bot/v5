<?php

namespace Tests\Unit;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\Disposition\DispositionInProgressOrderScope;
use App\Services\Disposition\DispositionKpiService;
use App\Services\Disposition\DispositionReminderService;
use App\Support\DispositionSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispositionKpiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database unavailable: '.$exception->getMessage());
        }
    }

    public function test_both_slots_fill_percent_for_today(): void
    {
        if (! Schema::hasTable('disposition_entries')) {
            $this->markTestSkipped('disposition_entries table is not migrated.');
        }

        $user = User::factory()->create();
        $date = Carbon::today()->toDateString();

        $filledOrder = $this->createInTransitOrder($user);
        $emptyOrder = $this->createInTransitOrder($user);

        DispositionEntry::query()->create([
            'order_id' => $filledOrder->id,
            'date' => $date,
            'slot' => DispositionSlot::Morning->value,
            'location' => 'Москва',
            'recorded_at' => now(),
            'recorded_by' => $user->id,
        ]);
        DispositionEntry::query()->create([
            'order_id' => $filledOrder->id,
            'date' => $date,
            'slot' => DispositionSlot::Evening->value,
            'location' => 'Тула',
            'recorded_at' => now(),
            'recorded_by' => $user->id,
        ]);

        $service = new DispositionKpiService(
            new DispositionInProgressOrderScope,
            app(DispositionReminderService::class),
        );

        $metrics = $service->metricsForUser($user, $date, true);

        $this->assertSame(2, $metrics['orders_in_progress']);
        $this->assertSame(1, $metrics['both_slots_filled_count']);
        $this->assertSame(50.0, $metrics['both_slots_fill_percent']);
    }

    private function createInTransitOrder(User $user): Order
    {
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $leg = OrderLeg::factory()->create([
            'order_id' => $order->id,
            'sequence' => 1,
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'actual_date' => Carbon::today()->toDateString(),
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => null,
        ]);

        return $order;
    }
}
