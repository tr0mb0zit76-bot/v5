<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\OrderStatusLog;
use App\Models\PaymentSchedule;
use App\Models\RoutePoint;
use App\Services\OrderDocumentRequirementService;
use App\Services\OrderStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @param  list<array{key: string, label: string, completed: bool}>  $checklist
     */
    private function serviceWithChecklist(array $checklist): OrderStatusService
    {
        $mock = Mockery::mock(OrderDocumentRequirementService::class);
        $mock->shouldReceive('checklistForOrder')->andReturn($checklist);

        $this->instance(OrderDocumentRequirementService::class, $mock);

        return new OrderStatusService($mock);
    }

    /**
     * @param  list<RoutePoint>  $points
     */
    private function orderWithLegPoints(array $points, ?Carbon $aggregateLoading = null, ?Carbon $aggregateUnloading = null): Order
    {
        $leg = new OrderLeg;
        $leg->setRelation('routePoints', collect($points));

        $order = new Order;
        $order->loading_date = $aggregateLoading;
        $order->unloading_date = $aggregateUnloading;
        $order->payment_statuses = [];
        $order->salary_paid = 0;
        $order->setRelation('legs', collect([$leg]));

        return $order;
    }

    public function test_planned_route_dates_only_stays_new_even_if_order_aggregate_dates_set(): void
    {
        $order = $this->orderWithLegPoints(
            [
                new RoutePoint([
                    'type' => 'loading',
                    'sequence' => 1,
                    'planned_date' => Carbon::today(),
                    'actual_date' => null,
                ]),
                new RoutePoint([
                    'type' => 'unloading',
                    'sequence' => 2,
                    'planned_date' => Carbon::tomorrow(),
                    'actual_date' => null,
                ]),
            ],
            Carbon::today(),
            Carbon::tomorrow(),
        );

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => false],
        ]);

        $this->assertSame('new', $service->resolve($order));
    }

    public function test_actual_loading_without_unloading_is_in_progress(): void
    {
        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::today(),
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'planned_date' => Carbon::tomorrow(),
                'actual_date' => null,
            ]),
        ]);

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => false],
        ]);

        $this->assertSame('in_progress', $service->resolve($order));
    }

    public function test_actual_unloading_with_incomplete_documents_is_documents(): void
    {
        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::yesterday(),
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'actual_date' => Carbon::today(),
            ]),
        ]);

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => false],
        ]);

        $this->assertSame('documents', $service->resolve($order));
    }

    public function test_actual_unloading_with_complete_documents_and_unpaid_is_payment(): void
    {
        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::yesterday(),
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'actual_date' => Carbon::today(),
            ]),
        ]);

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => true],
        ]);

        $this->assertSame('payment', $service->resolve($order));
    }

    public function test_all_paid_with_documents_complete_is_closed(): void
    {
        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::yesterday(),
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'actual_date' => Carbon::today(),
            ]),
        ]);
        $order->payment_statuses = [
            'customer' => ['paid' => true],
            'carrier' => ['paid' => true],
        ];
        $order->salary_paid = 100;

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => true],
        ]);

        $this->assertSame('closed', $service->resolve($order));
    }

    public function test_closed_when_payment_schedules_are_settled_even_without_payment_statuses_json(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('payment_schedules table is unavailable.');
        }

        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::yesterday(),
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'actual_date' => Carbon::today(),
            ]),
        ]);
        $order->payment_statuses = null;
        $order->salary_paid = 100;
        $order->save();

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 1000,
            'paid_amount' => 1000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 800,
            'paid_amount' => 800,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => true],
        ]);

        $this->assertSame('closed', $service->resolve($order->fresh()));
    }

    public function test_closed_when_salary_accrual_fully_paid_via_payroll_module(): void
    {
        if (! Schema::hasTable('salary_accruals')) {
            $this->markTestSkipped('salary_accruals table is unavailable.');
        }

        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::yesterday(),
            ]),
            new RoutePoint([
                'type' => 'unloading',
                'sequence' => 2,
                'actual_date' => Carbon::today(),
            ]),
        ]);
        $order->payment_statuses = null;
        $order->salary_paid = 0;
        $order->save();

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 1000,
            'paid_amount' => 1000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 800,
            'paid_amount' => 800,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $periodId = DB::table('salary_periods')->insertGetId([
            'period_start' => Carbon::today()->startOfMonth()->toDateString(),
            'period_end' => Carbon::today()->endOfMonth()->toDateString(),
            'period_type' => 'h2',
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_accruals')->insert([
            'period_id' => $periodId,
            'user_id' => 1,
            'order_id' => $order->id,
            'order_date_snapshot' => Carbon::today()->toDateString(),
            'delta_snapshot' => 1000,
            'salary_amount' => 500,
            'customer_rate_snapshot' => 1000,
            'paid_customer_amount_at_accrual' => 1000,
            'payable_amount_computed' => 500,
            'paid_amount_fact' => 500,
            'unpaid_amount' => 0,
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => true],
        ]);

        $this->assertSame('closed', $service->resolve($order->fresh()));
    }

    public function test_sync_stored_status_writes_status_log(): void
    {
        if (! Schema::hasTable('order_status_logs')) {
            $this->markTestSkipped('order_status_logs table is unavailable.');
        }

        $order = new Order;
        $order->status = 'payment';
        $order->setRelation('legs', collect());

        $service = $this->serviceWithChecklist([]);

        $derived = $service->syncStoredStatus($order, 1);

        $this->assertSame('payment', $derived);
        $this->assertSame(0, OrderStatusLog::query()->count());
    }

    public function test_requested_cancelled_wins(): void
    {
        $order = new Order;
        $order->setRelation('legs', collect());
        $service = $this->serviceWithChecklist([]);

        $this->assertSame('cancelled', $service->resolve($order, 'cancelled'));
    }

    public function test_requested_disruption_wins(): void
    {
        $order = new Order;
        $order->setRelation('legs', collect());
        $service = $this->serviceWithChecklist([]);

        $this->assertSame('disruption', $service->resolve($order, 'disruption'));
    }

    public function test_has_fact_loading_on_route_false_when_only_planned_points(): void
    {
        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'planned_date' => Carbon::today(),
                'actual_date' => null,
            ]),
        ]);

        $service = $this->serviceWithChecklist([]);

        $this->assertFalse($service->hasFactOfLoadingOnRoute($order));
    }

    public function test_has_fact_loading_on_route_true_when_actual_loading(): void
    {
        $order = $this->orderWithLegPoints([
            new RoutePoint([
                'type' => 'loading',
                'sequence' => 1,
                'actual_date' => Carbon::today(),
            ]),
        ]);

        $service = $this->serviceWithChecklist([]);

        $this->assertTrue($service->hasFactOfLoadingOnRoute($order));
    }

    public function test_legacy_order_without_route_points_uses_order_date_columns(): void
    {
        $order = new Order;
        $order->loading_date = Carbon::today();
        $order->unloading_date = Carbon::tomorrow();
        $order->payment_statuses = [];
        $order->salary_paid = 0;
        $order->setRelation('legs', collect([new OrderLeg]));

        $order->legs->first()->setRelation('routePoints', collect());

        $service = $this->serviceWithChecklist([
            ['key' => 'a', 'label' => 'Документ', 'completed' => false],
        ]);

        $this->assertSame('documents', $service->resolve($order));
        $this->assertFalse($service->hasFactOfLoadingOnRoute($order));
    }
}
