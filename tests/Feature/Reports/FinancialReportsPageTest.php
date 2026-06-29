<?php

namespace Tests\Feature\Reports;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialReportsPageTest extends TestCase
{
    public function test_supervisor_can_open_reports_and_see_abc_payload(): void
    {
        $role = Role::query()->create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'visibility_areas' => ['reports', 'orders'],
            'visibility_scopes' => ['orders' => 'all'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $c1 = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'Клиент А',
        ]);
        $c2 = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'Клиент Б',
        ]);

        Order::factory()->create([
            'manager_id' => $user->id,
            'customer_id' => $c1->id,
            'order_date' => '2026-04-10',
            'customer_rate' => 80000,
            'status' => 'new',
        ]);
        Order::factory()->create([
            'manager_id' => $user->id,
            'customer_id' => $c1->id,
            'order_date' => '2026-04-12',
            'customer_rate' => 20000,
            'status' => 'new',
        ]);
        $orderForPayment = Order::factory()->create([
            'manager_id' => $user->id,
            'customer_id' => $c2->id,
            'order_date' => '2026-04-15',
            'customer_rate' => 15000,
            'status' => 'new',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $orderForPayment->id,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 30000,
            'planned_date' => '2026-04-20',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'tab' => 'abc',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('tab', 'abc')
            ->has('abc.rows', 2)
            ->where('abc.total_revenue', 115000)
        );
    }

    public function test_manager_without_reports_area_gets_403(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['dashboard', 'orders'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }
}
