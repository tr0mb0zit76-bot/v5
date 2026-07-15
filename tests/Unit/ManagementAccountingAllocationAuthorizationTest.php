<?php

namespace Tests\Unit;

use App\Models\ManagementStatementLine;
use App\Models\ManagementStatementLineSplit;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingAllocationService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagementAccountingAllocationAuthorizationTest extends TestCase
{
    public function test_own_scope_can_allocate_own_order_schedule(): void
    {
        $actor = $this->makeManagementUser('own');
        $schedule = $this->createSchedule($actor);
        $line = $this->createAllocationLine();

        $this->allocateSingle($actor, $line, $schedule);

        $this->assertAllocationRecorded($line, $schedule);
    }

    public function test_department_scope_can_allocate_colleague_order_schedule(): void
    {
        $actor = $this->makeManagementUser('department');
        $colleague = $this->makeManagementUser('own');
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Управленческий учёт '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$actor, $colleague] as $member) {
            DB::table('department_user')->insert([
                'department_id' => $departmentId,
                'user_id' => $member->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $schedule = $this->createSchedule($colleague);
        $line = $this->createAllocationLine();

        $this->allocateSingle($actor, $line, $schedule);

        $this->assertAllocationRecorded($line, $schedule);
    }

    public function test_all_scope_can_allocate_foreign_order_schedule(): void
    {
        $actor = $this->makeManagementUser('all');
        $foreignManager = User::factory()->create();
        $schedule = $this->createSchedule($foreignManager);
        $line = $this->createAllocationLine();

        $this->allocateSingle($actor, $line, $schedule);

        $this->assertAllocationRecorded($line, $schedule);
    }

    public function test_own_scope_rejects_foreign_schedule_without_side_effects(): void
    {
        $actor = $this->makeManagementUser('own');
        $schedule = $this->createSchedule(User::factory()->create());
        $line = $this->createAllocationLine();

        $this->assertDeniedWithoutSideEffects(
            fn (): ManagementStatementLine => $this->allocateSingle($actor, $line, $schedule),
            $line,
            [$schedule],
        );
    }

    public function test_management_permission_is_required_for_operational_allocation(): void
    {
        $actor = $this->makeManagementUser('own', false);
        $schedule = $this->createSchedule($actor);
        $line = $this->createAllocationLine();

        $this->assertDeniedWithoutSideEffects(
            fn (): ManagementStatementLine => $this->allocateSingle($actor, $line, $schedule),
            $line,
            [$schedule],
        );
    }

    public function test_split_with_foreign_schedule_is_rejected_before_any_side_effect(): void
    {
        $actor = $this->makeManagementUser('own');
        $ownSchedule = $this->createSchedule($actor);
        $foreignSchedule = $this->createSchedule(User::factory()->create());
        $line = $this->createAllocationLine(1000);

        $this->assertDeniedWithoutSideEffects(
            fn (): ManagementStatementLine => app(ManagementAccountingAllocationService::class)->allocateLine(
                $line,
                [
                    'allocation_type' => 'operational',
                    'allocations' => [
                        ['payment_schedule_id' => $ownSchedule->id, 'amount' => 500],
                        ['payment_schedule_id' => $foreignSchedule->id, 'amount' => 500],
                    ],
                ],
                $actor,
            ),
            $line,
            [$ownSchedule, $foreignSchedule],
        );
    }

    private function makeManagementUser(string $scope, bool $canManagementAccounting = true): User
    {
        $role = Role::query()->create([
            'name' => 'management_allocator_'.uniqid(),
            'display_name' => 'Management allocator',
            'permissions' => [],
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => $scope],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'can_management_accounting' => $canManagementAccounting,
            'is_active' => true,
        ]);
    }

    private function createSchedule(User $manager): PaymentSchedule
    {
        $orderId = $this->insertOrderRow([
            'order_number' => 'AUTH-'.uniqid(),
            'manager_id' => $manager->id,
        ]);

        return PaymentSchedule::query()->create([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
            'status' => 'pending',
            'planned_date' => '2026-07-15',
        ]);
    }

    private function createAllocationLine(float $amount = 1000): ManagementStatementLine
    {
        return $this->createManagementStatementLine([
            'direction' => 'in',
            'amount' => $amount,
            'description' => 'Проверка авторизации графика оплат',
        ]);
    }

    private function allocateSingle(
        User $actor,
        ManagementStatementLine $line,
        PaymentSchedule $schedule,
    ): ManagementStatementLine {
        return app(ManagementAccountingAllocationService::class)->allocateLine(
            $line,
            [
                'allocation_type' => 'operational',
                'payment_schedule_id' => $schedule->id,
                'amount' => (float) $line->amount,
            ],
            $actor,
        );
    }

    private function assertAllocationRecorded(ManagementStatementLine $line, PaymentSchedule $schedule): void
    {
        $this->assertSame('allocated', $line->fresh()->status);
        $this->assertSame(1000.0, (float) $schedule->fresh()->paid_amount);
        $this->assertSame(
            1,
            PaymentSchedulePaymentEvent::query()
                ->where('transaction_reference', 'mgmt:'.$line->id)
                ->count(),
        );
    }

    /**
     * @param  list<PaymentSchedule>  $schedules
     */
    private function assertDeniedWithoutSideEffects(
        Closure $allocation,
        ManagementStatementLine $line,
        array $schedules,
    ): void {
        try {
            $allocation();
            $this->fail('Operational allocation should have been denied.');
        } catch (AuthorizationException) {
            $this->assertSame('pending', $line->fresh()->status);
            $this->assertSame(
                0,
                ManagementStatementLineSplit::query()
                    ->where('management_statement_line_id', $line->id)
                    ->count(),
            );
            $this->assertSame(
                0,
                PaymentSchedulePaymentEvent::query()
                    ->where('transaction_reference', 'like', 'mgmt:'.$line->id.'%')
                    ->count(),
            );

            foreach ($schedules as $schedule) {
                $schedule->refresh();
                $this->assertSame(0.0, (float) $schedule->paid_amount);
                $this->assertSame(1000.0, (float) $schedule->remaining_amount);
                $this->assertSame('pending', $schedule->status);
            }
        }
    }
}
