<?php

namespace Tests\Feature;

use App\Models\SalaryPeriod;
use App\Models\User;
use App\Services\SalaryPayrollService;
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

    public function test_payout_over_available_returns_validation_error_not_500(): void
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

        $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-02-20',
            'delta' => 100000,
            'salary_accrued' => 50000,
            'customer_rate' => 200000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-02-16',
            'period_end' => '2026-02-28',
            'period_type' => 'h2',
        ])->assertRedirect();

        $periodId = (int) DB::table('salary_periods')->value('id');

        $this->actingAs($user)->post(route('finance.salary.periods.payouts.store', $periodId), [
            'user_id' => $user->id,
            'amount' => 999999,
            'payout_date' => '2026-02-25',
            'type' => 'salary',
        ])->assertRedirect()->assertSessionHasErrors('payout');

        $this->assertSame(0, (int) DB::table('salary_payouts')->count());
    }

    public function test_order_rows_are_scoped_to_period_and_hide_fully_paid(): void
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

        $orderA = $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-03-05',
            'delta' => 200000,
            'salary_accrued' => 100000,
            'customer_rate' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderB = $this->insertOrderRow([
            'manager_id' => $user->id,
            'order_date' => '2026-03-20',
            'delta' => 100000,
            'salary_accrued' => 50000,
            'customer_rate' => 150000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$orderA => '2026-03-10', $orderB => '2026-03-25'] as $orderId => $paidOn) {
            DB::table('payment_schedules')->insert(array_filter([
                'order_id' => $orderId,
                'party' => 'customer',
                'amount' => $orderId === $orderA ? 300000 : 150000,
                'paid_amount' => Schema::hasColumn('payment_schedules', 'paid_amount')
                    ? ($orderId === $orderA ? 300000 : 150000)
                    : null,
                'actual_date' => $paidOn,
                'status' => 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ], fn (mixed $value): bool => $value !== null));
        }

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'period_type' => 'h1',
        ])->assertRedirect();
        $periodH1 = (int) DB::table('salary_periods')->where('period_type', 'h1')->value('id');

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_start' => '2026-03-16',
            'period_end' => '2026-03-31',
            'period_type' => 'h2',
        ])->assertRedirect();
        $periodH2 = (int) DB::table('salary_periods')->where('period_type', 'h2')->value('id');

        $this->actingAs($user)->post(route('finance.salary.periods.approve', $periodH1))->assertRedirect();

        $accrualH1 = DB::table('salary_accruals')->where('period_id', $periodH1)->where('order_id', $orderA)->first();
        $this->assertNotNull($accrualH1);

        $this->actingAs($user)->post(route('finance.salary.periods.payouts.store', $periodH1), [
            'user_id' => $user->id,
            'amount' => (float) $accrualH1->salary_amount,
            'payout_date' => '2026-03-20',
            'type' => 'salary',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $service = app(SalaryPayrollService::class);
        $periodModelH1 = SalaryPeriod::query()->findOrFail($periodH1);
        $periodModelH2 = SalaryPeriod::query()->findOrFail($periodH2);

        $h1Rows = $service->orderRowsForPeriod($periodModelH1);
        $h2Rows = $service->orderRowsForPeriod($periodModelH2);

        $this->assertSame([], $h1Rows, 'Fully paid accruals must not appear in order rows.');
        $this->assertCount(1, $h2Rows);
        $this->assertSame($orderB, $h2Rows[0]['order_id']);
    }

    public function test_period_can_be_created_from_month_and_half(): void
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

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_month' => '2026-04',
            'period_type' => 'h1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $period = DB::table('salary_periods')->first();
        $this->assertNotNull($period);
        $this->assertSame('2026-04-01', substr((string) $period->period_start, 0, 10));
        $this->assertSame('2026-04-15', substr((string) $period->period_end, 0, 10));
        $this->assertSame('h1', $period->period_type);
    }

    public function test_soft_deleted_orders_are_hidden_from_salary_order_rows(): void
    {
        $this->assertTrue(Schema::hasColumn('orders', 'deleted_at'));

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
            'order_date' => '2026-05-05',
            'delta' => 200000,
            'salary_accrued' => 100000,
            'customer_rate' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_month' => '2026-05',
            'period_type' => 'h1',
        ])->assertRedirect();

        $periodId = (int) DB::table('salary_periods')->value('id');
        $this->assertSame(1, (int) DB::table('salary_accruals')->where('order_id', $orderId)->count());

        DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);

        $service = app(SalaryPayrollService::class);
        $period = SalaryPeriod::query()->findOrFail($periodId);
        $this->assertSame([], $service->orderRowsForPeriod($period));
    }

    public function test_settle_removed_order_closes_unpaid_accrual(): void
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
            'order_date' => '2026-05-20',
            'delta' => 359800,
            'salary_accrued' => 179900,
            'customer_rate' => 2240000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('finance.salary.periods.store'), [
            'period_month' => '2026-05',
            'period_type' => 'h2',
        ])->assertRedirect();

        $this->artisan('salary:settle-removed-order', ['orderId' => $orderId])
            ->assertSuccessful();

        $accrual = DB::table('salary_accruals')->where('order_id', $orderId)->first();
        $this->assertNotNull($accrual);
        $this->assertSame('0.00', number_format((float) $accrual->unpaid_amount, 2, '.', ''));
        $this->assertSame('179900.00', number_format((float) $accrual->paid_amount_fact, 2, '.', ''));
        $this->assertSame('closed', DB::table('orders')->where('id', $orderId)->value('status'));
    }
}
