<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Services\Finance\PaymentSchedulePaymentReversalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchedulePaymentReversalServiceTest extends TestCase
{
    public function test_reverse_event_resets_first_payment_on_schedule(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);

        $order = Order::query()->create(['order_number' => 'AA-1']);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 10000,
            'paid_amount' => 10000,
            'remaining_amount' => 0,
            'actual_date' => '2026-06-01',
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'manual:1',
            'status' => 'paid',
        ]);

        $event = PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $order->id,
            'payment_schedule_id' => $schedule->id,
            'party' => 'customer',
            'amount' => 10000,
            'payment_date' => '2026-06-01',
            'payment_method' => 'bank_transfer',
            'recorded_by' => $user->id,
        ]);

        app(PaymentSchedulePaymentReversalService::class)->reverseEvent($event, $user, 'Тест');

        $schedule->refresh();
        $event->refresh();

        $this->assertSame('0.00', $schedule->paid_amount);
        $this->assertSame('10000.00', $schedule->remaining_amount);
        $this->assertSame('pending', $schedule->status);
        $this->assertNull($schedule->actual_date);
        $this->assertNotNull($event->reversed_at);
        $this->assertSame($user->id, $event->reversed_by);
    }

    public function test_reverse_by_management_line_id_finds_mgmt_reference(): void
    {
        $user = User::query()->create([
            'name' => 'Finance',
            'email' => 'finance@example.com',
            'password' => bcrypt('secret'),
        ]);

        $order = Order::query()->create(['order_number' => 'AA-2']);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 5000,
            'paid_amount' => 5000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $event = PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $order->id,
            'payment_schedule_id' => $schedule->id,
            'party' => 'carrier',
            'amount' => 5000,
            'payment_date' => '2026-06-02',
            'transaction_reference' => 'mgmt:42',
            'recorded_by' => $user->id,
        ]);

        $reversed = app(PaymentSchedulePaymentReversalService::class)->reverseByManagementLineId(42, $user);

        $this->assertNotNull($reversed);
        $this->assertNotNull($reversed->reversed_at);
        $this->assertSame('pending', $schedule->fresh()->status);
    }

    public function test_reverse_event_without_reversal_columns_deletes_event(): void
    {
        Schema::dropIfExists('payment_schedule_payment_events');

        Schema::create('payment_schedule_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->string('party', 16);
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'legacy-admin@example.com',
            'password' => bcrypt('secret'),
        ]);

        $order = Order::query()->create(['order_number' => 'AA-legacy']);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 3000,
            'paid_amount' => 3000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $event = PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $order->id,
            'payment_schedule_id' => $schedule->id,
            'party' => 'customer',
            'amount' => 3000,
            'payment_date' => '2026-06-03',
            'recorded_by' => $user->id,
        ]);

        app(PaymentSchedulePaymentReversalService::class)->reverseEvent($event, $user, 'Legacy');

        $this->assertDatabaseMissing('payment_schedule_payment_events', ['id' => $event->id]);
        $this->assertSame('pending', $schedule->fresh()->status);
    }

    public function test_reverse_by_management_line_id_restores_schedule_without_event(): void
    {
        $user = User::query()->create([
            'name' => 'Finance',
            'email' => 'finance-fallback@example.com',
            'password' => bcrypt('secret'),
        ]);

        $order = Order::query()->create(['order_number' => 'AA-fallback']);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 78000,
            'paid_amount' => 78000,
            'remaining_amount' => 0,
            'transaction_reference' => 'mgmt:99',
            'status' => 'paid',
        ]);

        app(PaymentSchedulePaymentReversalService::class)->reverseByManagementLineId(99, $user);

        $schedule->refresh();

        $this->assertSame('pending', $schedule->status);
        $this->assertSame('0.00', $schedule->paid_amount);
        $this->assertNull($schedule->transaction_reference);
    }
}
