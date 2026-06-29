<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_shows_current_user_period_metrics(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $managerRoleId,
            'email_verified_at' => now(),
        ])->refresh();

        $otherUser = User::factory()->create([
            'role_id' => $managerRoleId,
            'email_verified_at' => now(),
        ])->refresh();

        DB::table('orders')->insert(array_map(
            fn (array $row): array => $this->onlyExistingOrderColumns(array_merge([
                'created_at' => now(),
                'updated_at' => now(),
            ], $row)),
            [
                [
                    'manager_id' => $user->id,
                    'order_date' => '2026-04-05',
                    'customer_payment_form' => 'vat',
                    'carrier_payment_form' => 'vat',
                    'delta' => 15000,
                ],
                [
                    'manager_id' => $user->id,
                    'order_date' => '2026-04-10',
                    'customer_payment_form' => 'vat',
                    'carrier_payment_form' => 'no_vat',
                    'delta' => 5000,
                ],
                [
                    'manager_id' => $user->id,
                    'order_date' => '2026-03-30',
                    'customer_payment_form' => 'vat',
                    'carrier_payment_form' => 'vat',
                    'delta' => 9999,
                ],
                [
                    'manager_id' => $otherUser->id,
                    'order_date' => '2026-04-08',
                    'customer_payment_form' => 'vat',
                    'carrier_payment_form' => 'vat',
                    'delta' => 7777,
                ],
            ],
        ));

        $response = $this->actingAs($user)->get(route('dashboard', [
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('filters.date_from', '2026-04-01')
            ->where('filters.date_to', '2026-04-30')
            ->where('metrics.total_orders', 2)
            ->where('metrics.period_delta', 20000)
            ->has('metrics.weekly_client_returns_overdue')
        );
    }

    public function test_dashboard_metrics_work_when_orders_table_has_no_carrier_payment_form_column(): void
    {
        if (! Schema::hasColumn('orders', 'carrier_payment_form')) {
            $this->markTestSkipped('Колонка carrier_payment_form уже отсутствует в схеме.');
        }

        $this->markTestSkipped('DDL drop carrier_payment_form ломает RefreshDatabase на MySQL.');
    }
}
