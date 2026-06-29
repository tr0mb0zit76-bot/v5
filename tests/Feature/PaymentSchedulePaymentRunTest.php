<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\PaymentScheduleSettlementPreserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchedulePaymentRunTest extends TestCase
{
    public function test_user_can_mark_and_clear_payment_run_rows(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        $user = $this->makePaymentScheduleUser();
        $order = Order::factory()->create(['manager_id' => $user->id]);
        $scheduleId = $this->insertPaymentSchedule([
            'order_id' => $order->id,
            'planned_date' => '2026-06-28',
        ]);

        $this->actingAs($user)
            ->patchJson(route('payment-schedules.payment-run'), [
                'payment_schedule_ids' => [$scheduleId],
                'payment_run_date' => '2026-06-29',
                'payment_run_note' => 'Оплатить сегодня',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'updated_ids' => [$scheduleId],
                'payment_run_date' => '2026-06-29',
            ]);

        $this->assertDatabaseHas('payment_schedules', [
            'id' => $scheduleId,
            'payment_run_date' => '2026-06-29',
            'payment_run_by' => $user->id,
            'payment_run_note' => 'Оплатить сегодня',
        ]);

        $this->actingAs($user)
            ->patchJson(route('payment-schedules.payment-run'), [
                'payment_schedule_ids' => [$scheduleId],
                'clear' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'updated_ids' => [$scheduleId],
                'payment_run_date' => null,
            ]);

        $this->assertDatabaseHas('payment_schedules', [
            'id' => $scheduleId,
            'payment_run_date' => null,
            'payment_run_by' => null,
            'payment_run_note' => null,
        ]);
    }

    public function test_finance_cash_flow_journal_includes_payment_run_fields(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        $user = $this->makePaymentScheduleUser();
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'order_number' => 'PAY-RUN-1',
        ]);
        $scheduleId = $this->insertPaymentSchedule([
            'order_id' => $order->id,
            'planned_date' => '2026-06-28',
            'payment_run_date' => '2026-06-29',
            'payment_run_by' => $user->id,
            'payment_run_note' => 'В дневной план',
        ]);

        $this->actingAs($user)
            ->get(route('finance.index', ['section' => 'cashflow']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cashFlowJournal.0.id', $scheduleId)
                ->where('cashFlowJournal.0.payment_run_date', '2026-06-29')
                ->where('cashFlowJournal.0.payment_run_by', $user->id)
                ->where('cashFlowJournal.0.payment_run_note', 'В дневной план'),
            );
    }

    public function test_payment_run_mark_is_preserved_when_schedule_rows_are_restored(): void
    {
        $this->skipIfPaymentRunColumnsAreMissing();

        $user = $this->makePaymentScheduleUser();
        $order = Order::factory()->create(['manager_id' => $user->id]);
        $scheduleId = $this->insertPaymentSchedule([
            'order_id' => $order->id,
            'planned_date' => '2026-06-28',
            'payment_run_date' => '2026-06-29',
            'payment_run_by' => $user->id,
            'payment_run_note' => 'Сохранить после пересборки',
        ]);

        $preserver = app(PaymentScheduleSettlementPreserver::class);
        $snapshot = $preserver->snapshot((int) $order->id);

        DB::table('payment_schedules')->where('id', $scheduleId)->delete();
        $newScheduleId = $this->insertPaymentSchedule([
            'order_id' => $order->id,
            'planned_date' => '2026-06-28',
        ]);

        $preserver->restore((int) $order->id, $snapshot);

        $this->assertDatabaseHas('payment_schedules', [
            'id' => $newScheduleId,
            'payment_run_date' => '2026-06-29',
            'payment_run_by' => $user->id,
            'payment_run_note' => 'Сохранить после пересборки',
        ]);
    }

    private function skipIfPaymentRunColumnsAreMissing(): void
    {
        foreach (['payment_run_date', 'payment_run_by', 'payment_run_note'] as $column) {
            if (! Schema::hasColumn('payment_schedules', $column)) {
                $this->markTestSkipped('Поля планирования оплат ещё не мигрированы.');
            }
        }
    }

    private function makePaymentScheduleUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'payment-run-test-'.uniqid(),
            'display_name' => 'Payment run test',
            'permissions' => json_encode(['payment_schedule_record_payment'], JSON_THROW_ON_ERROR),
            'visibility_areas' => json_encode(['orders', 'payment_schedules'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all', 'payment_schedules' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
            'is_active' => true,
        ]);
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
