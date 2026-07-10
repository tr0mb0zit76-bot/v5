<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalaryPayrollManagementTest extends TestCase
{
    public function test_can_create_recalculate_and_pay_salary_period(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'visibility_areas' => json_encode(['dashboard', 'settings_motivation', 'finance_salary'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-02-20',
            'delta' => 500000,
            'salary_accrued' => 250000,
            'customer_rate' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert(array_filter([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 1000000,
            'paid_amount' => Schema::hasColumn('payment_schedules', 'paid_amount') ? 1000000 : null,
            'actual_date' => '2026-02-22',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ], fn (mixed $value): bool => $value !== null));

        $createResponse = $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-02-16',
            'period_end' => '2026-02-28',
            'period_type' => 'h2',
            'notes' => 'Тестовый период',
        ]);
        $createResponse->assertRedirect();

        $periodId = DB::table('salary_periods')->value('id');
        $this->assertNotNull($periodId);

        $accrual = DB::table('salary_accruals')->where('period_id', $periodId)->first();
        $this->assertNotNull($accrual);
        $this->assertSame('250000.00', number_format((float) $accrual->salary_amount, 2, '.', ''));
        $this->assertSame('250000.00', number_format((float) $accrual->payable_amount_computed, 2, '.', ''));

        $payoutResponse = $this->actingAs($user)->post(
            route('finance.salary.periods.payouts.store', $periodId),
            [
                'user_id' => $user->id,
                'amount' => 100000,
                'payout_date' => '2026-02-25',
                'type' => 'salary',
            ]
        );
        $payoutResponse->assertRedirect();

        $this->assertDatabaseHas('salary_payouts', [
            'period_id' => $periodId,
            'user_id' => $user->id,
            'amount' => 100000,
        ]);

        $updatedAccrual = DB::table('salary_accruals')->where('id', $accrual->id)->first();
        $this->assertSame('100000.00', number_format((float) $updatedAccrual->paid_amount_fact, 2, '.', ''));

        $this->assertSame('100000.00', number_format((float) DB::table('orders')->where('id', $orderId)->value('salary_paid'), 2, '.', ''));
    }

    public function test_advance_can_be_paid_before_customer_payment_and_settled_after_recalculation(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'visibility_areas' => json_encode(['dashboard', 'settings_motivation', 'finance_salary'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-03-05',
            'delta' => 200000,
            'salary_accrued' => 100000,
            'customer_rate' => 400000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'period_type' => 'h1',
        ]);
        $createResponse->assertRedirect();

        $periodId = DB::table('salary_periods')->value('id');
        $accrual = DB::table('salary_accruals')->where('period_id', $periodId)->first();
        $this->assertSame('0.00', number_format((float) $accrual->payable_amount_computed, 2, '.', ''));

        $advanceResponse = $this->actingAs($user)->post(
            route('finance.salary.periods.payouts.store', $periodId),
            [
                'user_id' => $user->id,
                'amount' => 30000,
                'payout_date' => '2026-03-10',
                'type' => 'advance',
            ]
        );
        $advanceResponse->assertRedirect();
        $this->assertSame(0, DB::table('salary_payout_allocations')->count());

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 400000,
            'actual_date' => '2026-03-12',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recalculateResponse = $this->actingAs($user)->post(route('finance.salary.periods.recalculate', $periodId));
        $recalculateResponse->assertRedirect();

        $this->assertDatabaseHas('salary_payout_allocations', [
            'amount' => 30000,
        ]);

        $updatedAccrual = DB::table('salary_accruals')->where('period_id', $periodId)->first();
        $this->assertSame('30000.00', number_format((float) $updatedAccrual->paid_amount_fact, 2, '.', ''));

        $this->assertSame('30000.00', number_format((float) DB::table('orders')->where('id', $orderId)->value('salary_paid'), 2, '.', ''));
    }

    public function test_partial_customer_payment_is_reflected_in_accrual_snapshot(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'visibility_areas' => json_encode(['dashboard', 'settings_motivation', 'finance_salary'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-04-10',
            'delta' => 200000,
            'salary_accrued' => 100000,
            'customer_rate' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 1000000,
            'paid_amount' => 500000,
            'status' => 'pending',
            'actual_date' => '2026-04-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'period_type' => 'h1',
        ])->assertRedirect();

        $accrual = DB::table('salary_accruals')->first();
        $this->assertNotNull($accrual);
        $this->assertSame('500000.00', number_format((float) $accrual->paid_customer_amount_at_accrual, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $accrual->payable_amount_computed, 2, '.', ''));
    }

    public function test_salary_accrual_recalculates_from_current_delta_not_stale_order_field(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'supervisor',
            'visibility_areas' => json_encode(['dashboard', 'settings_motivation', 'finance_salary'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-04-10',
            'delta' => 359800,
            'salary_accrued' => 166400,
            'customer_rate' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'period_type' => 'h1',
        ])->assertRedirect();

        $accrual = DB::table('salary_accruals')->first();
        $this->assertNotNull($accrual);
        $this->assertSame('179900.00', number_format((float) $accrual->salary_amount, 2, '.', ''));

        $this->assertSame('179900.00', number_format((float) DB::table('orders')->where('id', $orderId)->value('salary_accrued'), 2, '.', ''));
    }
}
