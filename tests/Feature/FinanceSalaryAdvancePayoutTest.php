<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalaryPayout;
use App\Models\SalaryPeriod;
use App\Models\User;
use App\Services\SalaryPayrollService;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceSalaryAdvancePayoutTest extends TestCase
{
    #[Test]
    public function finance_can_store_advance_payout_for_period(): void
    {
        if (! Schema::hasTable('salary_periods') || ! Schema::hasTable('salary_payouts')) {
            $this->markTestSkipped('Salary tables not migrated');
        }

        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $employee = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $period = SalaryPeriod::query()->create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
            'period_type' => 'h1',
            'status' => 'approved',
            'notes' => null,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('finance.salary.periods.payouts.store', $period), [
            'user_id' => $employee->id,
            'amount' => 7500.50,
            'payout_date' => '2026-01-10',
            'type' => 'advance',
            'comment' => 'Карта',
        ])->assertRedirect();

        $payout = SalaryPayout::query()
            ->where('period_id', $period->id)
            ->where('user_id', $employee->id)
            ->first();

        $this->assertNotNull($payout);
        $this->assertSame('advance', $payout->type);
        $this->assertEqualsWithDelta(7500.50, (float) $payout->amount, 0.01);
        $this->assertSame('Карта', $payout->comment);

        $service = app(SalaryPayrollService::class);
        $summaries = $service->userSummariesForPeriod(SalaryPeriod::query()->find($period->id), null);
        $row = collect($summaries)->firstWhere('user_id', $employee->id);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(7500.50, (float) $row['advance_total'], 0.01);
        $this->assertEqualsWithDelta(7500.50, (float) $row['paid_total'], 0.01);
    }

    #[Test]
    public function finance_can_store_advance_without_salary_period(): void
    {
        if (! Schema::hasTable('salary_payouts')) {
            $this->markTestSkipped('Salary tables not migrated');
        }

        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $employee = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($admin)->post(route('finance.salary.advance-payouts.store'), [
            'user_id' => $employee->id,
            'amount' => 3000,
            'payout_date' => '2026-02-01',
            'comment' => 'Вне периода',
        ])->assertRedirect();

        $payout = SalaryPayout::query()
            ->whereNull('period_id')
            ->where('user_id', $employee->id)
            ->where('type', 'advance')
            ->first();

        $this->assertNotNull($payout);
        $this->assertEqualsWithDelta(3000.0, (float) $payout->amount, 0.01);
        $this->assertSame('Вне периода', $payout->comment);
    }
}
