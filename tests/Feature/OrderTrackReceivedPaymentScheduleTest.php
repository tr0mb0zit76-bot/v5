<?php

namespace Tests\Feature;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\OrderCompensationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackReceivedPaymentScheduleTest extends TestCase
{
    public function test_ottn_customer_planned_date_requires_full_checklist_and_both_original_dates(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('orders', 'track_received_date_customer_request')) {
            $this->markTestSkipped('Колонки request/closing или payment_schedules недоступны.');
        }

        $manager = $this->makeManagerUser();

        $order = $this->createOrderWithPaymentTerms([
            'manager_id' => $manager->id,
            'order_date' => '2026-06-01',
            'customer_rate' => 34000,
            'track_received_date_customer_request' => null,
            'track_received_date_customer_closing' => null,
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
        $this->assertNull(PaymentSchedule::query()->where('order_id', $order->id)->where('party', 'customer')->value('planned_date'));

        $this->attachSignedCustomerDocumentPackage($order);

        $order->forceFill([
            'track_received_date_customer_request' => '2026-06-05',
            'track_received_date_customer_closing' => '2026-06-08',
        ])->saveQuietly();

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh(['documents']));

        $after = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();

        $this->assertNotNull($after);
        $this->assertSame('2026-06-11', $after->planned_date?->toDateString());
    }

    public function test_ottn_carrier_planned_date_stays_empty_when_only_request_is_attached(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('orders', 'track_received_date_carrier_request')) {
            $this->markTestSkipped('Колонки request/closing или payment_schedules недоступны.');
        }

        $contractorId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Перевозчик по оригиналам',
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
                            'offset_days' => 3,
                            'offset_unit' => 'calendar_days',
                            'anchor' => 'last_unloading',
                            'basis' => 'ottn',
                        ],
                    ],
                ],
            ],
        ];

        $order = Order::factory()->create($this->onlyExistingOrderColumns([
            'order_date' => '2026-08-01',
            'track_received_date_carrier_request' => '2026-08-07',
            'track_received_date_carrier_closing' => null,
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

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'request',
            'status' => 'signed',
            'original_name' => 'carrier-request.pdf',
            'file_path' => 'orders/'.$order->id.'/carrier-request.pdf',
            'metadata' => ['party' => 'carrier', 'carrier_contractor_id' => $contractorId, 'flow' => 'uploaded'],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh(['documents']));

        $row = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'carrier')
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->planned_date);
    }

    public function test_cash_carrier_ottn_planned_date_appears_after_waybill_is_attached(): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->markTestSkipped('Таблица payment_schedules недоступна.');
        }

        $manager = $this->makeManagerUser();

        $contractorId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Перевозчик для OTTN cash',
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

        $waybill = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'waybill',
            'status' => 'signed',
            'original_name' => 'tsd.pdf',
            'file_path' => 'orders/'.$order->id.'/tsd.pdf',
            'metadata' => ['party' => 'carrier', 'flow' => 'uploaded'],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);
        $waybill->forceFill([
            'created_at' => '2026-06-02 10:00:00',
            'updated_at' => '2026-06-02 10:00:00',
        ])->saveQuietly();

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh(['documents']));

        $after = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'carrier')
            ->first();

        $this->assertNotNull($after);
        $this->assertSame('2026-06-09', $after->planned_date?->toDateString());
    }

    public function test_ottn_uses_later_originals_date_when_checklist_complete(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('orders', 'track_received_date_customer_request')) {
            $this->markTestSkipped('Колонки request/closing или payment_schedules недоступны.');
        }

        $order = $this->createOrderWithPaymentTerms([
            'order_date' => '2026-06-01',
            'customer_rate' => 34000,
            'track_received_date_customer_request' => '2026-06-05',
            'track_received_date_customer_closing' => '2026-06-20',
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

        $this->attachSignedCustomerDocumentPackage($order);

        app(OrderCompensationService::class)->resyncPaymentSchedulesForOrder($order->fresh(['documents']));

        $row = PaymentSchedule::query()
            ->where('order_id', $order->id)
            ->where('party', 'customer')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('2026-06-24', $row->planned_date?->toDateString());
    }

    private function attachSignedCustomerDocumentPackage(Order $order): void
    {
        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'request',
            'status' => 'signed',
            'original_name' => 'customer-request.pdf',
            'file_path' => 'orders/'.$order->id.'/customer-request.pdf',
            'metadata' => ['party' => 'customer', 'flow' => 'uploaded'],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'upd',
            'status' => 'signed',
            'original_name' => 'customer-upd.pdf',
            'file_path' => 'orders/'.$order->id.'/customer-upd.pdf',
            'metadata' => ['party' => 'customer', 'flow' => 'uploaded'],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);
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
