<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerformerPersistenceTest extends TestCase
{
    public function test_existing_order_persists_performer_replacement_after_reload(): void
    {
        $admin = $this->createAdminUser();

        $clientId = (int) DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldCarrierId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Старый перевозчик',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newCarrierId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Новый перевозчик',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int) $this->insertOrderRow([
            'order_number' => 'ORD-PR-1',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-03',
            'customer_id' => $clientId,
            'carrier_id' => $oldCarrierId,
            'status' => 'new',
            'performers' => json_encode([
                ['stage' => 'Плечо 1', 'contractor_id' => $oldCarrierId],
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = (int) DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'Плечо 1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leg_contractor_assignments')->insert([
            'order_leg_id' => $legId,
            'contractor_id' => $oldCarrierId,
            'assigned_at' => now(),
            'assigned_by' => $admin->id,
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-04',
            'order_number' => 'ORD-PR-1',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'Плечо 1', 'contractor_id' => $newCarrierId],
            ],
            'route_points' => [
                [
                    'stage' => 'Плечо 1',
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара',
                    'normalized_data' => [],
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 0,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'Плечо 1',
                        'contractor_id' => $newCarrierId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 0,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $response->assertRedirect(route('orders.edit', $orderId));

        $updatedLegId = (int) DB::table('order_legs')
            ->where('order_id', $orderId)
            ->value('id');

        $this->assertDatabaseHas('leg_contractor_assignments', [
            'order_leg_id' => $updatedLegId,
            'contractor_id' => $newCarrierId,
        ]);

        $reloadResponse = $this->actingAs($admin)->get(route('orders.edit', $orderId));
        $reloadResponse->assertOk();
        $reloadResponse->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.performers.0.contractor_id', $newCarrierId)
        );
    }

    public function test_edit_includes_carrier_from_financial_terms_when_leg_assignment_missing(): void
    {
        $admin = $this->createAdminUser();

        $clientId = (int) DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент FT',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = (int) DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик из фин. условий',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int) $this->insertOrderRow([
            'order_number' => 'ORD-FT-1',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-03',
            'customer_id' => $clientId,
            'carrier_id' => null,
            'status' => 'new',
            'performers' => json_encode([], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'Плечо 1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 50000,
            'client_currency' => 'RUB',
            'client_payment_terms' => null,
            'contractors_costs' => json_encode([
                [
                    'stage' => 'Плечо 1',
                    'contractor_id' => $carrierId,
                    'amount' => 30000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'total_cost' => 30000,
            'margin' => 20000,
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.performers.0.contractor_id', $carrierId)
        );
    }

    private function createAdminUser(): User
    {
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => json_encode(['orders'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
