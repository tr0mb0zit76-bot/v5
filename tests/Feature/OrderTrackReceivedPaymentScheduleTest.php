<?php

namespace Tests\Feature;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\OrderCompensationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackReceivedPaymentScheduleTest extends TestCase
{
    public function test_ottn_customer_planned_date_appears_after_track_received_is_set(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        $manager = $this->makeManagerUser();

        $order = $this->createOrderWithPaymentTerms([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'customer_rate' => 34000,
            'track_received_date_customer' => null,
        ], [
            'client' => [
                'payment_schedule' => [
                    'installments' => [
                        [
                            'percent' => 100,
                            'offset_days' => 3,
                            'offset_unit' => 'bank_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'ottn',
                        ],
                    ],
                ],
            ],
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $before = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();

        $this->assertNotNull($before);
        $this->assertNull($before->planned_date);

        $this->actingAs($manager)->patch(route('orders.inline-update', $order->id), [
            'field' => 'track_received_date_customer',
            'value' => '2026-06-05',
            'wizard_context' => true,
        ])->assertRedirect(route('orders.edit', $order->id));

        $after = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();

        $this->assertNotNull($after);
        $this->assertSame('2026-06-10', $after->planned_date?->toDateString());
    }

    public function test_cash_carrier_ottn_planned_date_appears_after_track_received_is_set(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        $manager = $this->makeManagerUser();

        $contractorId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Перевозчик для OTTN',
            'is_active' => true,
            'is_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contractorsCosts = [
            [
                'contractor_id' => $contractorId,
                'payment_form' => 'cash',
                'amount' => 8000,
                'payment_schedule' => [
                    'installments' => [
                        [
                            'percent' => 100,
                            'amount' => 8000,
                            'offset_days' => 5,
                            'offset_unit' => 'bank_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'ottn',
                        ],
                    ],
                ],
            ],
        ];

        $order = Order::factory()->create($this->onlyExistingOrderColumns([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'unloading_date' => '2026-06-17',
            'track_received_date_carrier' => null,
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

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh());

        $before = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'carrier')
            ->first();

        $this->assertNotNull($before);
        $this->assertNull($before->planned_date);

        $this->actingAs($manager)->patch(route('orders.inline-update', $order->id), [
            'field' => 'track_received_date_carrier',
            'value' => '2026-06-02',
            'wizard_context' => true,
        ])->assertRedirect(route('orders.edit', $order->id));

        $after = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'carrier')
            ->first();

        $this->assertNotNull($after);
        $this->assertSame('2026-06-09', $after->planned_date?->toDateString());
    }

    private function makeManagerUser(): User
    {
        $roleId = DB::table('roles')->where('name', 'manager')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'visibility_areas' => json_encode(['orders'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create(['role_id' => $roleId]);
    }
}
