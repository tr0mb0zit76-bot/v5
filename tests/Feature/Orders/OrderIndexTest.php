<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCompensationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderIndexTest extends TestCase
{
    public function test_admin_sees_all_orders(): void
    {
        $adminRoleId = $this->createRole('admin');
        $managerRoleId = $this->createRole('manager');

        $admin = User::factory()->create();
        $manager = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $admin->role_id = $adminRoleId;
        $manager->role_id = $managerRoleId;

        $this->createOrder('ADMIN-001', $admin->id);
        $this->createOrder('MANAGER-001', $manager->id);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->where('roleKey', 'admin')
            ->has('rows', 2)
            ->has('orderColumns')
        );
    }

    public function test_orders_are_returned_in_ascending_id_order(): void
    {
        $adminRoleId = $this->createRole('admin');
        $admin = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);
        $admin->role_id = $adminRoleId;

        $this->createOrder('FIRST-ORDER', $admin->id);
        $this->createOrder('SECOND-ORDER', $admin->id);
        $this->createOrder('THIRD-ORDER', $admin->id);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 3)
            ->where('rows.0.order_number', 'FIRST-ORDER')
            ->where('rows.1.order_number', 'SECOND-ORDER')
            ->where('rows.2.order_number', 'THIRD-ORDER')
        );
    }

    public function test_orders_index_includes_carrier_payment_form_from_financial_terms_when_orders_column_missing(): void
    {
        if (Schema::hasColumn('orders', 'carrier_payment_form')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('carrier_payment_form');
            });
        }

        $adminRoleId = $this->createRole('admin');
        $admin = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);
        $admin->role_id = $adminRoleId;

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'GRID-CPF',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_id' => $carrierId,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 5,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 100000,
            'client_currency' => 'RUB',
            'contractors_costs' => json_encode([
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'amount' => 70000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'total_cost' => 70000,
            'margin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1, fn (Assert $row) => $row
                ->where('order_number', 'GRID-CPF')
                ->where('carrier_payment_form', 'no_vat')
                ->etc()
            )
        );
    }

    public function test_orders_index_carrier_payment_term_matches_financial_terms_schedule(): void
    {
        $adminRoleId = $this->createRole('admin');
        $admin = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);
        $admin->role_id = $adminRoleId;

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier CPT',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'GRID-CPT',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_id' => $carrierId,
            'carrier_payment_term' => 'устаревшая строка',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 5,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 100000,
            'client_currency' => 'RUB',
            'contractors_costs' => json_encode([
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'amount' => 70000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [
                        'has_prepayment' => true,
                        'prepayment_ratio' => 40,
                        'prepayment_days' => 2,
                        'prepayment_mode' => 'fttn',
                        'postpayment_days' => 10,
                        'postpayment_mode' => 'ottn',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'total_cost' => 70000,
            'margin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1, fn (Assert $row) => $row
                ->where('order_number', 'GRID-CPT')
                ->where('carrier_payment_term', fn (string $term): bool => str_contains($term, '40%')
                    && str_contains($term, '2')
                    && str_contains($term, 'сканам')
                    && str_contains($term, '60%')
                    && str_contains($term, '10')
                    && str_contains($term, 'оригиналам'))
                ->etc()
            )
        );
    }

    public function test_manager_sees_only_their_own_orders(): void
    {
        $managerRoleId = $this->createRole('manager');

        $manager = User::factory()->create();
        $otherManager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        DB::table('users')->where('id', $otherManager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;
        $otherManager->role_id = $managerRoleId;

        $this->createOrder('OWN-001', $manager->id);
        $this->createOrder('OTHER-001', $otherManager->id);

        $response = $this->actingAs($manager)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->where('roleKey', 'manager')
            ->has('rows', 1, fn (Assert $row) => $row
                ->where('order_number', 'OWN-001')
                ->etc()
            )
        );
    }

    public function test_supervisor_sees_all_orders(): void
    {
        $supervisorRoleId = $this->createRole('supervisor');
        $managerRoleId = $this->createRole('manager');

        $supervisor = User::factory()->create();
        $firstManager = User::factory()->create();
        $secondManager = User::factory()->create();

        DB::table('users')->where('id', $supervisor->id)->update(['role_id' => $supervisorRoleId]);
        DB::table('users')->where('id', $firstManager->id)->update(['role_id' => $managerRoleId]);
        DB::table('users')->where('id', $secondManager->id)->update(['role_id' => $managerRoleId]);

        $supervisor->role_id = $supervisorRoleId;

        $this->createOrder('MANAGER-A-001', $firstManager->id);
        $this->createOrder('MANAGER-B-001', $secondManager->id);

        $response = $this->actingAs($supervisor)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->where('roleKey', 'supervisor')
            ->has('rows', 2)
            ->has('orderColumns')
        );
    }

    public function test_clerk_with_all_orders_scope_sees_all_orders(): void
    {
        $clerkRoleId = $this->createRole('clerk', ['orders' => 'all']);
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);

        $clerk = User::factory()->create();
        $firstManager = User::factory()->create();
        $secondManager = User::factory()->create();

        DB::table('users')->where('id', $clerk->id)->update(['role_id' => $clerkRoleId]);
        DB::table('users')->where('id', $firstManager->id)->update(['role_id' => $managerRoleId]);
        DB::table('users')->where('id', $secondManager->id)->update(['role_id' => $managerRoleId]);
        $clerk->role_id = $clerkRoleId;

        $this->createOrder('MANAGER-A-001', $firstManager->id);
        $this->createOrder('MANAGER-B-001', $secondManager->id);

        $response = $this->actingAs($clerk)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('roleKey', 'clerk')
            ->has('rows', 2)
            ->has('orderInlineEditableFields')
        );
    }

    public function test_clerk_can_inline_update_track_number_on_foreign_order(): void
    {
        if (! Schema::hasColumn('orders', 'track_number_customer')) {
            $this->markTestSkipped('Колонка track_number_customer недоступна.');
        }

        $clerkRoleId = $this->createRole('clerk', ['orders' => 'all']);
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);

        $clerk = User::factory()->create();
        $manager = User::factory()->create();

        DB::table('users')->where('id', $clerk->id)->update(['role_id' => $clerkRoleId]);
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $clerk->role_id = $clerkRoleId;

        $orderId = $this->createOrder('CLERK-TRACK', $manager->id);

        $response = $this->actingAs($clerk)->patch(route('orders.inline-update', $orderId), [
            'field' => 'track_number_customer',
            'value' => 'RU123456789',
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'track_number_customer' => 'RU123456789',
        ]);
    }

    public function test_clerk_cannot_inline_update_carrier_rate_on_foreign_order(): void
    {
        $clerkRoleId = $this->createRole('clerk', ['orders' => 'all']);
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);

        $clerk = User::factory()->create();
        $manager = User::factory()->create();

        DB::table('users')->where('id', $clerk->id)->update(['role_id' => $clerkRoleId]);
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $clerk->role_id = $clerkRoleId;

        $orderId = $this->createOrder('CLERK-NO-RATE', $manager->id);

        $this->actingAs($clerk)->patch(route('orders.inline-update', $orderId), [
            'field' => 'carrier_rate',
            'value' => 5000,
        ])->assertForbidden();
    }

    public function test_manager_can_delete_own_order_before_loading(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('DELETE-ME', $manager->id);

        $response = $this->actingAs($manager)->delete(route('orders.destroy', $orderId));

        $response->assertRedirect(route('orders.index'));
        $this->assertSoftDeleted('orders', ['id' => $orderId]);
    }

    public function test_deleting_order_removes_payment_schedule_financial_records(): void
    {
        if (! Schema::hasTable('payment_schedules') || ! Schema::hasTable('payment_schedule_payment_events')) {
            $this->markTestSkipped('Payment schedule tables are not available in this test database.');
        }

        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('DELETE-PAYMENTS', $manager->id);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'prepayment',
            'amount' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 500,
            'payment_date' => '2026-06-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->delete(route('orders.destroy', $orderId));

        $response->assertRedirect(route('orders.index'));
        $this->assertSoftDeleted('orders', ['id' => $orderId]);
        $this->assertDatabaseMissing('payment_schedules', ['order_id' => $orderId]);
        $this->assertDatabaseMissing('payment_schedule_payment_events', ['order_id' => $orderId]);
    }

    public function test_manager_can_inline_update_own_order_fields(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('INLINE-EDIT', $manager->id);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'track_number_customer',
            'value' => 'TRACK-001',
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'track_number_customer' => 'TRACK-001',
            'updated_by' => $manager->id,
        ]);
    }

    public function test_inline_update_customer_rate_syncs_financial_terms_row(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('INLINE-SYNC', $manager->id);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 1000.00,
            'client_currency' => 'RUB',
            'contractors_costs' => json_encode([
                [
                    'stage' => 'leg_1',
                    'amount' => 400.00,
                    'currency' => 'RUB',
                ],
            ], JSON_THROW_ON_ERROR),
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'total_cost' => 400.00,
            'margin' => 600.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'customer_rate',
            'value' => 2500.50,
        ]);

        $response->assertRedirect(route('orders.index'));

        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'customer_rate' => 2500.50,
        ]);

        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => 2500.50,
        ]);
    }

    public function test_inline_update_payment_form_recalculates_kpi_for_period(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        DB::table('kpi_thresholds')->insert([
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.00',
                'threshold_to' => '0.50',
                'kpi_percent' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.00',
                'threshold_to' => '0.50',
                'kpi_percent' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.51',
                'threshold_to' => '1.00',
                'kpi_percent' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.51',
                'threshold_to' => '1.00',
                'kpi_percent' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier',
        ]);

        $firstOrderId = $this->insertOrderRow([
            'order_number' => 'PERIOD-1',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-10',
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_rate' => 70000,
            'carrier_payment_form' => 'vat',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 5,
            'delta' => 25000,
            'salary_accrued' => 12500,
            'salary_paid' => 0,
            'carrier_id' => $carrierId,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondOrderId = $this->insertOrderRow([
            'order_number' => 'PERIOD-2',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-12',
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_rate' => 70000,
            'carrier_payment_form' => 'no_vat',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 8,
            'delta' => 22000,
            'salary_accrued' => 11000,
            'salary_paid' => 0,
            'carrier_id' => $carrierId,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            [
                'order_id' => $firstOrderId,
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'contractors_costs' => json_encode([
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'vat',
                        'payment_schedule' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
                'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
                'total_cost' => 70000,
                'margin' => 25000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $secondOrderId,
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'contractors_costs' => json_encode([
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
                'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
                'total_cost' => 70000,
                'margin' => 22000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $secondOrderId), [
            'field' => 'carrier_payment_form',
            'value' => 'vat',
        ]);

        $response->assertRedirect(route('orders.index'));

        $this->assertDatabaseHasOrder([
            'id' => $firstOrderId,
            'kpi_percent' => '3.00',
            'delta' => '27000.00',
            'salary_accrued' => '13500.00',
        ]);

        $this->assertDatabaseHasOrder([
            'id' => $secondOrderId,
            'kpi_percent' => '3.00',
            'delta' => '27000.00',
            'salary_accrued' => '13500.00',
        ]);

        // Check that financial_terms contractors_costs is updated with new payment_form
        $this->assertContractorsCostsContainPaymentForm($secondOrderId, 'vat');
    }

    public function test_inline_update_order_date_recalculates_kpi_for_periods(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        DB::table('kpi_thresholds')->insert([
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.00',
                'threshold_to' => '0.50',
                'kpi_percent' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.00',
                'threshold_to' => '0.50',
                'kpi_percent' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.51',
                'threshold_to' => '1.00',
                'kpi_percent' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.51',
                'threshold_to' => '1.00',
                'kpi_percent' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier',
        ]);

        $firstOrderId = $this->insertOrderRow([
            'order_number' => 'PERIOD-1',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-10', // First half of April
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_rate' => 70000,
            'carrier_payment_form' => 'vat',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 5,
            'delta' => 25000,
            'salary_accrued' => 12500,
            'salary_paid' => 0,
            'carrier_id' => $carrierId,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondOrderId = $this->insertOrderRow([
            'order_number' => 'PERIOD-2',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-12', // First half of April
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_rate' => 70000,
            'carrier_payment_form' => 'no_vat',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 8,
            'delta' => 22000,
            'salary_accrued' => 11000,
            'salary_paid' => 0,
            'carrier_id' => $carrierId,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            [
                'order_id' => $firstOrderId,
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'contractors_costs' => json_encode([
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'vat',
                        'payment_schedule' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
                'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
                'total_cost' => 70000,
                'margin' => 25000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $secondOrderId,
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'contractors_costs' => json_encode([
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 70000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
                'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
                'total_cost' => 70000,
                'margin' => 22000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $secondOrderId), [
            'field' => 'order_date',
            'value' => '2026-04-20', // Second half of April
        ]);

        $response->assertRedirect(route('orders.index'));

        // First order remains in first half after peer moves to another period
        $this->assertDatabaseHasOrder([
            'id' => $firstOrderId,
            'kpi_percent' => '5.00',
            'delta' => '25000.00',
            'salary_accrued' => '12500.00',
        ]);

        // Second order moved to second half alone with vat/no_vat category (3% KPI)
        $this->assertDatabaseHasOrder([
            'id' => $secondOrderId,
            'kpi_percent' => '3.00',
            'delta' => '27000.00',
            'salary_accrued' => '13500.00',
        ]);
    }

    public function test_inline_update_carrier_rate_creates_and_syncs_financial_terms_row(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'INLINE-CARRIER-SYNC',
            'manager_id' => $manager->id,
            'carrier_id' => $carrierId,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'carrier_rate',
            'value' => 3210.45,
        ]);

        $response->assertRedirect(route('orders.index'));

        $this->assertOrderCarrierRate($orderId, 3210.45);

        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
        ]);

        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);
        $decoded = json_decode($contractorsCosts, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(3210.45, round((float) ($decoded[0]['amount'] ?? 0), 2));
        $this->assertSame($carrierId, (int) ($decoded[0]['contractor_id'] ?? 0));
    }

    public function test_inline_carrier_payment_form_syncs_financial_terms_when_orders_carrier_columns_dropped(): void
    {
        if (Schema::hasColumn('orders', 'carrier_payment_form')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('carrier_payment_form');
            });
        }

        if (Schema::hasColumn('orders', 'carrier_rate')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('carrier_rate');
            });
        }

        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'NO-CARRIER-COLS',
            'manager_id' => $manager->id,
            'order_date' => '2026-04-10',
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat',
            'carrier_id' => $carrierId,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 5,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 100000,
            'client_currency' => 'RUB',
            'contractors_costs' => json_encode([
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'amount' => 70000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'total_cost' => 70000,
            'margin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'carrier_payment_form',
            'value' => 'vat',
        ]);

        $response->assertRedirect(route('orders.index'));

        $this->assertContractorsCostsContainPaymentForm($orderId, 'vat');

        $this->restoreTestDatabaseSchema();
    }

    public function test_inline_update_rate_still_works_when_financial_terms_table_is_missing(): void
    {
        $this->markTestSkipped('На u_tromb financial_terms всегда есть после migrate; legacy-сценарий не актуален для RefreshDatabase.');
    }

    public function test_manager_can_inline_update_date_field(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('INLINE-DATE', $manager->id);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'track_sent_date_customer',
            'value' => '2026-04-02',
        ]);

        $response->assertRedirect(route('orders.index'));
        $storedDate = DB::table('orders')->where('id', $orderId)->value('track_sent_date_customer');

        $this->assertNotNull($storedDate);
        $this->assertStringStartsWith('2026-04-02', (string) $storedDate);
    }

    public function test_manager_cannot_inline_update_other_manager_order(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        DB::table('users')->where('id', $otherManager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('FOREIGN-ORDER', $otherManager->id);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'track_number_customer',
            'value' => 'TRACK-002',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'track_number_customer' => null,
        ]);
    }

    public function test_manager_cannot_delete_in_progress_order(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('LOCKED-ORDER', $manager->id, '2026-04-02', 'in_progress');

        $response = $this->actingAs($manager)->delete(route('orders.destroy', $orderId));

        $response->assertForbidden();
        $this->assertDatabaseHasOrder(['id' => $orderId, 'deleted_at' => null]);
    }

    public function test_deleting_already_soft_deleted_order_redirects_without_404(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('ALREADY-DELETED', $manager->id);
        DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);

        $response = $this->actingAs($manager)->delete(route('orders.destroy', $orderId));

        $response->assertRedirect(route('orders.index'));
    }

    public function test_calculate_order_delta_uses_carrier_sum_from_financial_terms(): void
    {
        DB::table('kpi_thresholds')->insert([
            [
                'deal_type' => 'direct',
                'threshold_from' => '0.00',
                'threshold_to' => '1.00',
                'kpi_percent' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deal_type' => 'indirect',
                'threshold_from' => '0.00',
                'threshold_to' => '1.00',
                'kpi_percent' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $adminRoleId = $this->createRole('admin');
        $admin = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'Carrier Delta',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'DELTA-FT',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'customer_rate' => 600000,
            'carrier_rate' => 0,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'carrier_id' => $carrierId,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 0,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_terms')->insert([
            'order_id' => $orderId,
            'client_price' => 600000,
            'client_currency' => 'RUB',
            'contractors_costs' => json_encode([
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'amount' => 400000,
                    'currency' => 'RUB',
                    'payment_form' => 'vat',
                    'payment_schedule' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'additional_costs' => json_encode([], JSON_THROW_ON_ERROR),
            'total_cost' => 400000,
            'margin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::query()->findOrFail($orderId);
        $calc = app(OrderCompensationService::class)->calculateOrder($order);

        $this->assertSame(182000.0, $calc['delta']);
    }

    public function test_orders_index_shows_route_point_dates_and_kind_for_grid(): void
    {
        $adminRoleId = $this->createRole('admin');
        $admin = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);
        $admin->role_id = $adminRoleId;

        $orderId = $this->insertOrderRow([
            'order_number' => 'ROUTE-KIND',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 0,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = (int) DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
        ]);

        DB::table('route_points')->insert([
            'order_leg_id' => $legId,
            'address_id' => null,
            'type' => 'loading',
            'sequence' => 1,
            'planned_date' => '2026-05-01',
            'actual_date' => null,
        ]);

        DB::table('route_points')->insert([
            'order_leg_id' => $legId,
            'address_id' => null,
            'type' => 'unloading',
            'sequence' => 2,
            'planned_date' => '2026-05-05',
            'actual_date' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1, fn (Assert $row) => $row
                ->where('order_number', 'ROUTE-KIND')
                ->where('loading_date', '2026-05-01')
                ->where('unloading_date', '2026-05-05')
                ->where('loading_date_route_kind', 'planned')
                ->where('unloading_date_route_kind', 'planned')
                ->etc()
            )
        );
    }

    public function test_orders_index_shows_actual_route_dates_when_set(): void
    {
        $adminRoleId = $this->createRole('admin');
        $admin = User::factory()->create();

        DB::table('users')->where('id', $admin->id)->update(['role_id' => $adminRoleId]);
        $admin->role_id = $adminRoleId;

        $orderId = $this->insertOrderRow([
            'order_number' => 'ROUTE-ACT',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-10',
            'loading_date' => '2026-04-01',
            'unloading_date' => '2026-04-02',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 0,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => 'new',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = (int) DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
        ]);

        DB::table('route_points')->insert([
            'order_leg_id' => $legId,
            'address_id' => null,
            'type' => 'loading',
            'sequence' => 1,
            'planned_date' => '2026-05-01',
            'actual_date' => '2026-05-10',
        ]);

        DB::table('route_points')->insert([
            'order_leg_id' => $legId,
            'address_id' => null,
            'type' => 'unloading',
            'sequence' => 2,
            'planned_date' => '2026-05-05',
            'actual_date' => '2026-05-12',
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1, fn (Assert $row) => $row
                ->where('order_number', 'ROUTE-ACT')
                ->where('loading_date', '2026-05-10')
                ->where('unloading_date', '2026-05-12')
                ->where('loading_date_route_kind', 'actual')
                ->where('unloading_date_route_kind', 'actual')
                ->etc()
            )
        );
    }

    public function test_orders_index_can_delete_for_manager_follows_status(): void
    {
        $mgrRoleId = $this->createRole('manager');
        $manager = User::factory()->create();
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $mgrRoleId]);
        $manager->role_id = $mgrRoleId;

        $this->createOrder('CD-NEW', (int) $manager->id, null, 'new');
        $this->createOrder('CD-PROG', (int) $manager->id, null, 'in_progress');

        $this->actingAs($manager)->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.order_number', 'CD-NEW')
                ->where('rows.0.can_delete', true)
                ->where('rows.1.order_number', 'CD-PROG')
                ->where('rows.1.can_delete', false)
            );
    }

    public function test_orders_index_can_delete_for_supervisor_requires_new_or_in_progress(): void
    {
        $supRoleId = $this->createRole('supervisor');
        $mgrRoleId = $this->createRole('manager');
        $owner = User::factory()->create();
        DB::table('users')->where('id', $owner->id)->update(['role_id' => $mgrRoleId]);

        $supervisor = User::factory()->create();
        DB::table('users')->where('id', $supervisor->id)->update(['role_id' => $supRoleId]);
        $supervisor->role_id = $supRoleId;

        $this->createOrder('CD-SNEW', (int) $owner->id, null, 'new');
        $this->createOrder('CD-SDOC', (int) $owner->id, null, 'documents');

        $this->actingAs($supervisor)->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.order_number', 'CD-SNEW')
                ->where('rows.0.can_delete', true)
                ->where('rows.1.order_number', 'CD-SDOC')
                ->where('rows.1.can_delete', false)
            );
    }

    public function test_manager_cannot_inline_update_carrier_rate_when_order_is_in_progress(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('IN-PROG-MGR', $manager->id, null, 'in_progress');

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'carrier_rate',
            'value' => 5000,
        ]);

        $response->assertSessionHasErrors('field');
    }

    public function test_supervisor_can_inline_update_carrier_rate_when_order_is_in_progress(): void
    {
        $supervisorRoleId = $this->createRole('supervisor');
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);

        $supervisor = User::factory()->create();
        $manager = User::factory()->create();

        DB::table('users')->where('id', $supervisor->id)->update(['role_id' => $supervisorRoleId]);
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $supervisor->role_id = $supervisorRoleId;

        $orderId = $this->createOrder('IN-PROG-SUP', $manager->id, null, 'in_progress');

        $response = $this->actingAs($supervisor)->patch(route('orders.inline-update', $orderId), [
            'field' => 'carrier_rate',
            'value' => 7777.5,
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHasOrder([
            'id' => $orderId,
            'carrier_rate' => 7777.5,
        ]);
    }

    public function test_orders_index_marks_financial_fields_editable_for_supervisor_on_in_progress_order(): void
    {
        $supervisorRoleId = $this->createRole('supervisor');
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);

        $supervisor = User::factory()->create();
        $manager = User::factory()->create();

        DB::table('users')->where('id', $supervisor->id)->update(['role_id' => $supervisorRoleId]);
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $supervisor->role_id = $supervisorRoleId;

        $this->createOrder('IN-PROG-GRID', $manager->id, null, 'in_progress');

        $this->actingAs($supervisor)->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1, fn (Assert $row) => $row
                    ->where('order_number', 'IN-PROG-GRID')
                    ->where('can_edit_financial_fields', true)
                    ->etc()
                )
            );
    }

    public function test_orders_index_can_delete_admin_always_true_for_advanced_status(): void
    {
        $admRoleId = $this->createRole('admin');
        $mgrRoleId = $this->createRole('manager');
        $owner = User::factory()->create();
        DB::table('users')->where('id', $owner->id)->update(['role_id' => $mgrRoleId]);

        $admin = User::factory()->create();
        DB::table('users')->where('id', $admin->id)->update(['role_id' => $admRoleId]);
        $admin->role_id = $admRoleId;

        $this->createOrder('CD-ADM-DOC', (int) $owner->id, null, 'documents');

        $this->actingAs($admin)->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1, fn (Assert $row) => $row
                    ->where('order_number', 'CD-ADM-DOC')
                    ->where('can_delete', true)
                    ->etc()
                )
            );
    }

    private function createRole(string $name, array $visibilityScopes = []): int
    {
        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => ucfirst($name),
            'visibility_scopes' => json_encode($visibilityScopes, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(string $orderNumber, int $managerId, ?string $loadingDate = null, string $status = 'new'): int
    {
        return $this->insertOrderRow([
            'order_number' => $orderNumber,
            'manager_id' => $managerId,
            'loading_date' => $loadingDate,
            'order_date' => '2026-04-10',
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'kpi_percent' => 0,
            'delta' => 0,
            'salary_accrued' => 0,
            'salary_paid' => 0,
            'status' => $status,
            'is_active' => true,
        ]);
    }
}
