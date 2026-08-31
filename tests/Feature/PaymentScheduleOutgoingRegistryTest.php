<?php

namespace Tests\Feature;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\PaymentSchedule;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\Finance\FinanceOverviewService;
use App\Services\OrderCompensationService;
use App\Support\PaymentScheduleSettlementPreserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentScheduleOutgoingRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_carrier_row_gets_auto_registry_date_on_resync(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $contractorId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Перевозчик реестра',
            'is_active' => true,
            'is_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contractorsCosts = [
            [
                'contractor_id' => $contractorId,
                'payment_form' => 'vat_22',
                'amount' => 48000,
                'payment_schedule' => [
                    'installments' => [
                        [
                            'percent' => 100,
                            'amount' => 48000,
                            'offset_days' => 1,
                            'offset_unit' => 'calendar_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'unloading',
                        ],
                    ],
                ],
            ],
        ];

        $order = Order::factory()->create($this->onlyExistingOrderColumns([
            'order_date' => '2026-06-01',
            'carrier_id' => $contractorId,
            'unloading_date' => null,
            'wizard_state' => [
                'financial_term' => [
                    'contractors_costs' => $contractorsCosts,
                ],
            ],
        ]));

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'contractors_costs' => $contractorsCosts,
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
            'actual_date' => '2026-06-02',
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $row = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'carrier')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('2026-06-03', $row->planned_date?->toDateString());
        $this->assertSame('2026-06-04', $row->payment_run_date?->toDateString());
        $this->assertNull($row->payment_run_by);
    }

    public function test_customer_row_does_not_get_auto_registry_date(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $order = $this->createOrderWithPaymentTerms([
            'order_date' => '2026-06-01',
            'customer_rate' => 100000,
            'unloading_date' => '2026-06-02',
        ], [
            'client' => [
                'payment_schedule' => [
                    'installments' => [
                        [
                            'percent' => 100,
                            'offset_days' => 1,
                            'offset_unit' => 'calendar_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'unloading',
                        ],
                    ],
                ],
            ],
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $row = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('2026-06-03', $row->planned_date?->toDateString());
        $this->assertNull($row->payment_run_date);
    }

    public function test_manual_registry_date_is_preserved_on_resync(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        $user = User::factory()->create();
        $order = Order::factory()->create(['manager_id' => $user->id]);

        $scheduleId = $this->insertPaymentSchedule([
            'order_id' => $order->id,
            'party' => 'carrier',
            'planned_date' => '2026-06-03',
            'payment_run_date' => '2026-06-11',
            'payment_run_by' => $user->id,
            'payment_run_note' => 'Вручную на четверг',
        ]);

        $preserver = app(PaymentScheduleSettlementPreserver::class);
        $snapshot = $preserver->snapshot((int) $order->id);

        DB::table('payment_schedules')->where('id', $scheduleId)->delete();
        $newScheduleId = $this->insertPaymentSchedule([
            'order_id' => $order->id,
            'party' => 'carrier',
            'planned_date' => '2026-06-03',
            'payment_run_date' => '2026-06-04',
        ]);

        $preserver->restore((int) $order->id, $snapshot);

        $this->assertDatabaseHas('payment_schedules', [
            'id' => $newScheduleId,
            'payment_run_date' => '2026-06-11',
            'payment_run_by' => $user->id,
            'payment_run_note' => 'Вручную на четверг',
        ]);
    }

    public function test_cash_flow_stats_include_outgoing_registry_summary(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        Carbon::setTestNow(Carbon::parse('2026-06-08'));

        $carrierA = (int) DB::table('contractors')->insertGetId([
            'name' => 'Перевозчик A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $carrierB = (int) DB::table('contractors')->insertGetId([
            'name' => 'Перевозчик B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrderRow([
            'order_number' => 'REG-1',
            'carrier_id' => $carrierA,
        ]);

        DB::table('payment_schedules')->insert([
            [
                'order_id' => $orderId,
                'party' => 'carrier',
                'type' => 'final',
                'amount' => 30000,
                'paid_amount' => 0,
                'remaining_amount' => 30000,
                'status' => 'pending',
                'planned_date' => '2026-06-05',
                'payment_run_date' => '2026-06-09',
                'counterparty_id' => $carrierA,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'party' => 'carrier',
                'type' => 'final',
                'amount' => 20000,
                'paid_amount' => 0,
                'remaining_amount' => 20000,
                'status' => 'pending',
                'planned_date' => '2026-06-05',
                'payment_run_date' => '2026-06-09',
                'counterparty_id' => $carrierB,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $stats = app(FinanceOverviewService::class)->cashFlowStats(null);

        $this->assertSame('2026-06-09', $stats['outgoing_registry']['tuesday']['date']);
        $this->assertSame(50000.0, $stats['outgoing_registry']['tuesday']['amount']);
        $this->assertSame(2, $stats['outgoing_registry']['tuesday']['counterparties']);
    }

    private function skipIfPaymentRunColumnsAreMissing(): void
    {
        foreach (['payment_run_date', 'payment_run_by', 'payment_run_note'] as $column) {
            if (! Schema::hasColumn('payment_schedules', $column)) {
                $this->markTestSkipped('Поля реестра оплат ещё не мигрированы.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertPaymentSchedule(array $attributes): int
    {
        $row = array_merge([
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 10000,
            'paid_amount' => 0,
            'remaining_amount' => 10000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes);

        $row = array_filter(
            $row,
            fn (mixed $value, string $key): bool => Schema::hasColumn('payment_schedules', $key),
            ARRAY_FILTER_USE_BOTH,
        );

        return (int) DB::table('payment_schedules')->insertGetId($row);
    }
}
