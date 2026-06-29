<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\PaymentSchedule;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\OrderCompensationService;
use App\Support\OrderRouteMilestoneDateResolver;
use App\Support\PaymentInstallmentPlanner;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentScheduleUnloadingDateTest extends TestCase
{
    public function test_customer_planned_date_uses_actual_unloading_on_route_when_order_column_empty(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        $manager = User::factory()->create();
        $unloadingActual = '2026-06-02';

        $order = $this->createOrderWithPaymentTerms([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'customer_rate' => 100000,
            'unloading_date' => null,
        ], [
            'client' => [
                'payment_schedule' => [
                    'installments' => [
                        [
                            'percent' => 100,
                            'offset_days' => 5,
                            'offset_unit' => 'calendar_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'unloading',
                        ],
                    ],
                ],
            ],
        ]);

        $leg = OrderLeg::factory()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'description' => 'leg_1',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'planned_date' => null,
            'actual_date' => $unloadingActual,
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $row = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-06-07', $row->planned_date?->toDateString());
    }

    public function test_cash_customer_schedule_uses_unloading_instead_of_fttn_documents(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        $manager = User::factory()->create();
        $unloadingActual = '2026-06-02';

        $order = $this->createOrderWithPaymentTerms([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'customer_rate' => 100000,
            'customer_payment_form' => 'cash',
            'unloading_date' => null,
        ], [
            'client' => [
                'payment_schedule' => [
                    'installments' => [
                        [
                            'percent' => 100,
                            'offset_days' => 3,
                            'offset_unit' => 'calendar_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'fttn',
                        ],
                    ],
                ],
            ],
        ]);

        $leg = OrderLeg::factory()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'description' => 'leg_1',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'planned_date' => null,
            'actual_date' => $unloadingActual,
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $row = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('2026-06-05', $row->planned_date?->toDateString());
    }

    public function test_installment_anchor_last_unloading_uses_actual_route_date(): void
    {
        $manager = User::factory()->create();

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'unloading_date' => null,
        ]);

        $leg = OrderLeg::factory()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'description' => 'leg_1',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'planned_date' => null,
            'actual_date' => '2026-06-10',
        ]);

        $planned = PaymentInstallmentPlanner::plannedDateForInstallment(
            [
                'anchor' => 'last_unloading',
                'offset_days' => 3,
                'offset_unit' => 'calendar_days',
            ],
            $order->fresh(),
        );

        $this->assertSame('2026-06-13', $planned);
    }

    public function test_route_milestone_resolver_syncs_order_unloading_date_from_actual_route_point(): void
    {
        $order = Order::factory()->create([
            'unloading_date' => null,
        ]);

        $leg = OrderLeg::factory()->create([
            'order_id' => $order->id,
            'sequence' => 1,
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => '2026-06-15',
        ]);

        $synced = OrderRouteMilestoneDateResolver::syncToOrder($order->fresh());

        $this->assertSame('2026-06-15', $synced->unloading_date?->toDateString());
    }

    public function test_resolve_unloading_date_prefers_actual_over_stale_order_column(): void
    {
        $order = Order::factory()->create([
            'unloading_date' => '2026-05-01',
        ]);

        $leg = OrderLeg::factory()->create([
            'order_id' => $order->id,
            'sequence' => 1,
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'actual_date' => '2026-06-20',
        ]);

        $resolved = OrderRouteMilestoneDateResolver::resolveUnloadingDate($order->fresh());

        $this->assertSame('2026-06-20', $resolved);
    }
}
