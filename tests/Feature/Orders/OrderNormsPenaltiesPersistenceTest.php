<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderNormsPenaltiesPersistenceTest extends TestCase
{
    public function test_order_wizard_persists_norms_penalties_in_wizard_state(): void
    {
        if (! Schema::hasColumn('orders', 'wizard_state')) {
            $this->markTestSkipped('orders.wizard_state column not present');
        }

        $admin = $this->createAdminUser();

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Собственная',
            'inn' => '1111111111',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Заказчик',
            'inn' => '2222222222',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'inn' => '3333333333',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $normsPayload = [
            'client_norms_penalties' => [
                'miss_amount' => 1000,
                'miss_currency' => 'USD',
                'downtime_amount' => 500,
                'downtime_currency' => 'RUB',
                'fine_amount' => 100,
                'fine_currency' => 'EUR',
                'penalty_terms' => '0,1% в день',
                'norm_loading_hours' => 24,
                'norm_customs_hours' => 48.5,
                'norm_unloading_hours' => 12,
            ],
            'carrier_norms_by_leg' => [
                [
                    'stage' => 'leg_1',
                    'miss_amount' => 2000,
                    'miss_currency' => 'RUB',
                    'downtime_amount' => null,
                    'downtime_currency' => 'RUB',
                    'fine_amount' => null,
                    'fine_currency' => 'RUB',
                    'penalty_terms' => '',
                    'norm_loading_hours' => 8,
                    'norm_customs_hours' => null,
                    'norm_unloading_hours' => 6,
                ],
            ],
        ];

        $basePayload = [
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-08-01',
            'order_number' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Москва',
                    'normalized_data' => [],
                    'planned_date' => '2026-08-02',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Тула',
                    'normalized_data' => [],
                    'planned_date' => '2026-08-03',
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_22',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 5,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 80000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 3,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
                ...$normsPayload,
            ],
        ];

        $this->actingAs($admin)
            ->post(route('orders.store'), $basePayload)
            ->assertRedirect();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);

        $wizardState = is_array($order->wizard_state) ? $order->wizard_state : [];
        $this->assertSame(1000.0, (float) data_get($wizardState, 'financial_term.client_norms_penalties.miss_amount'));
        $this->assertSame('USD', data_get($wizardState, 'financial_term.client_norms_penalties.miss_currency'));
        $this->assertSame('0,1% в день', data_get($wizardState, 'financial_term.client_norms_penalties.penalty_terms'));
        $this->assertSame(2000.0, (float) data_get($wizardState, 'financial_term.carrier_norms_by_leg.0.miss_amount'));

        $this->actingAs($admin)
            ->get(route('orders.edit', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Wizard')
                ->where('order.financial_term.client_norms_penalties.miss_amount', 1000)
                ->where('order.financial_term.client_norms_penalties.penalty_terms', '0,1% в день')
                ->where('order.financial_term.carrier_norms_by_leg.0.miss_amount', 2000)
            );
    }

    private function createAdminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'visibility_areas' => json_encode(['orders', 'dashboard', 'settings', 'contractors']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
