<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalaryPayrollManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('salary_payout_allocations');
        Schema::dropIfExists('salary_payouts');
        Schema::dropIfExists('salary_accruals');
        Schema::dropIfExists('salary_periods');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->date('order_date')->nullable();
            $table->decimal('delta', 12, 2)->nullable();
            $table->decimal('kpi_percent', 5, 2)->nullable();
            $table->decimal('salary_accrued', 12, 2)->default(0);
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('party', ['customer', 'carrier']);
            $table->decimal('amount', 12, 2);
            $table->date('actual_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        Schema::create('salary_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_type', 10);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_accruals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id');
            $table->date('order_date_snapshot')->nullable();
            $table->decimal('delta_snapshot', 14, 2)->default(0);
            $table->decimal('salary_amount', 14, 2)->default(0);
            $table->decimal('customer_rate_snapshot', 14, 2)->default(0);
            $table->decimal('paid_customer_amount_at_accrual', 14, 2)->default(0);
            $table->decimal('payable_amount_computed', 14, 2)->default(0);
            $table->decimal('paid_amount_fact', 14, 2)->default(0);
            $table->decimal('unpaid_amount', 14, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 14, 2);
            $table->date('payout_date');
            $table->string('type', 20)->default('salary');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_payout_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_id');
            $table->unsignedBigInteger('accrual_id');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

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

        $orderId = DB::table('orders')->insertGetId([
            'manager_id' => $user->id,
            'order_date' => '2026-02-20',
            'delta' => 500000,
            'salary_accrued' => 250000,
            'customer_rate' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 1000000,
            'actual_date' => '2026-02-22',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $orderId = DB::table('orders')->insertGetId([
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
    }
}
