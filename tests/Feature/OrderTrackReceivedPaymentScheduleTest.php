<?php

namespace Tests\Feature;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderCompensationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackReceivedPaymentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_ottn_customer_planned_date_appears_after_track_received_is_set(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'permissions' => [],
            'visibility_areas' => ['orders'],
        ]);

        $manager = User::factory()->create(['role_id' => $role->id]);

        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'customer_rate' => 34000,
            'track_received_date_customer' => null,
            'payment_terms' => json_encode([
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
            ], JSON_THROW_ON_ERROR),
        ]);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'client_price' => 34000,
            'payment_terms_snapshot' => $order->payment_terms,
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
}
