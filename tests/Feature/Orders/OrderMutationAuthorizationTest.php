<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderMutationAuthorizationTest extends TestCase
{
    public function test_accounting_handoff_denied_for_foreign_order_with_finance_salary(): void
    {
        if (! Schema::hasColumn('orders', 'accounting_handoff_at')) {
            $this->markTestSkipped('orders.accounting_handoff_at is unavailable.');
        }

        $accountant = $this->makeUser(['pipeline', 'orders', 'finance_salary'], ['orders' => 'own']);
        $foreignManager = User::factory()->create(['role_id' => $accountant->role_id]);
        $order = $this->createClosedOrder(['manager_id' => $foreignManager->id]);

        $this->actingAs($accountant)
            ->post(route('pipeline.orders.accounting-handoff', $order))
            ->assertForbidden();
    }

    public function test_basic_terms_promote_denied_for_foreign_order(): void
    {
        $promoter = $this->makeUser(['orders', 'contractors'], ['orders' => 'own']);
        $foreignManager = User::factory()->create(['role_id' => $promoter->role_id]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Чужой заказчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::factory()->create([
            'manager_id' => $foreignManager->id,
            'customer_id' => $customerId,
            'customer_basic_terms' => ['Пункт для контрагента'],
        ]);

        $this->actingAs($promoter)
            ->post(route('orders.basic-terms.promote', $order), [
                'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            ])
            ->assertForbidden();
    }

    public function test_department_scope_allows_colleague_payment_schedule_payment_run(): void
    {
        if (! Schema::hasTable('department_user')
            || ! Schema::hasTable('departments')
            || ! Schema::hasColumn('payment_schedules', 'payment_run_date')) {
            $this->markTestSkipped('department or payment_run columns are unavailable.');
        }

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Dept payments '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::query()->create([
            'name' => 'dept_payment_'.uniqid(),
            'display_name' => 'Dept payment',
            'permissions' => ['payment_schedule_record_payment'],
            'visibility_areas' => ['orders', 'payment_schedules'],
            'visibility_scopes' => ['orders' => 'department', 'payment_schedules' => 'department'],
        ]);

        $colleague = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $viewer = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        foreach ([$colleague, $viewer] as $member) {
            DB::table('department_user')->insert([
                'department_id' => $departmentId,
                'user_id' => $member->id,
                'is_primary' => true,
                'receives_approvals' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $order = Order::factory()->create(['manager_id' => $colleague->id]);

        $scheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $order->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 5000,
            'planned_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->patchJson(route('payment-schedules.payment-run'), [
                'payment_schedule_ids' => [$scheduleId],
                'payment_run_date' => now()->toDateString(),
            ])
            ->assertOk();
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'mutation_auth_'.uniqid(),
            'display_name' => 'Mutation auth test',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createClosedOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'status' => 'closed',
            'payment_statuses' => [
                'customer' => ['paid' => true],
                'carrier' => ['paid' => true],
            ],
            'salary_paid' => 100,
        ], $attributes));
    }
}
