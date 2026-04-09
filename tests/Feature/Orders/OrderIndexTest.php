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
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('cargo_leg');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('financial_terms');
        Schema::dropIfExists('kpi_thresholds');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('order_legs');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->string('company_code', 10)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedTinyInteger('site_id')->nullable();
            $table->date('order_date')->nullable();
            $table->date('loading_date')->nullable();
            $table->date('unloading_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->string('customer_payment_form', 50)->nullable();
            $table->string('customer_payment_term', 50)->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->string('carrier_payment_term', 50)->nullable();
            $table->decimal('additional_expenses', 12, 2)->default(0);
            $table->decimal('delta', 12, 2)->nullable();
            $table->decimal('kpi_percent', 5, 2)->nullable();
            $table->decimal('salary_accrued', 12, 2)->default(0);
            $table->decimal('salary_paid', 12, 2)->default(0);
            $table->string('status', 50)->default('new');
            $table->string('manual_status', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('ai_draft_id')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->json('ai_metadata')->nullable();
            $table->json('ati_response')->nullable();
            $table->string('ati_load_id')->nullable();
            $table->timestamp('ati_published_at')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('upd_number')->nullable();
            $table->string('waybill_number')->nullable();
            $table->string('track_number_customer')->nullable();
            $table->date('track_sent_date_customer')->nullable();
            $table->date('track_received_date_customer')->nullable();
            $table->string('track_number_carrier')->nullable();
            $table->date('track_sent_date_carrier')->nullable();
            $table->date('track_received_date_carrier')->nullable();
            $table->string('order_customer_number')->nullable();
            $table->date('order_customer_date')->nullable();
            $table->string('order_carrier_number')->nullable();
            $table->date('order_carrier_date')->nullable();
            $table->string('upd_carrier_number')->nullable();
            $table->date('upd_carrier_date')->nullable();
            $table->string('customer_contact_name')->nullable();
            $table->string('customer_contact_phone', 50)->nullable();
            $table->string('customer_contact_email')->nullable();
            $table->string('carrier_contact_name')->nullable();
            $table->string('carrier_contact_phone', 50)->nullable();
            $table->string('carrier_contact_email')->nullable();
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->json('payment_statuses')->nullable();
            $table->decimal('insurance', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('address_line')->nullable();
        });

        Schema::create('order_legs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->integer('sequence')->default(1);
        });

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->unsignedBigInteger('address_id')->nullable();
            $table->string('type');
            $table->integer('sequence')->default(1);
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_contact')->nullable();
            $table->string('sender_phone', 50)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_contact')->nullable();
            $table->string('recipient_phone', 50)->nullable();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
        });

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type');
            $table->string('number')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('financial_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->decimal('client_price', 12, 2)->nullable();
            $table->string('client_currency', 10)->nullable();
            $table->json('contractors_costs')->nullable();
            $table->json('additional_costs')->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->decimal('margin', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('deal_type', 50);
            $table->decimal('threshold_from', 5, 2);
            $table->decimal('threshold_to', 5, 2);
            $table->integer('kpi_percent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('status_to');
            $table->string('status_from')->nullable();
            $table->timestamps();
        });

        Schema::create('cargo_leg', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cargo_id');
            $table->unsignedBigInteger('order_leg_id');
        });
    }

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

        $orderId = (int) DB::table('orders')->insertGetId([
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

        $orderId = (int) DB::table('orders')->insertGetId([
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
                ->where('carrier_payment_term', '40% 2 дн ФТТН / 60% 10 дн ОТТН')
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
        );
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
        $this->assertDatabaseHas('orders', [
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

        $this->assertDatabaseHas('orders', [
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

        $firstOrderId = (int) DB::table('orders')->insertGetId([
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

        $secondOrderId = (int) DB::table('orders')->insertGetId([
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

        $this->assertDatabaseHas('orders', [
            'id' => $firstOrderId,
            'kpi_percent' => '5.00',
            'delta' => '25000.00',
            'salary_accrued' => '12500.00',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $secondOrderId,
            'carrier_payment_form' => 'vat',
            'kpi_percent' => '5.00',
            'delta' => '25000.00',
            'salary_accrued' => '12500.00',
        ]);

        // Check that financial_terms contractors_costs is updated with new payment_form
        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $secondOrderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);
        $this->assertStringContainsString('"payment_form":"vat"', $contractorsCosts);
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

        $firstOrderId = (int) DB::table('orders')->insertGetId([
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

        $secondOrderId = (int) DB::table('orders')->insertGetId([
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

        // First order remains in first half, now only direct deal, so KPI should be 5
        $this->assertDatabaseHas('orders', [
            'id' => $firstOrderId,
            'kpi_percent' => '5.00',
            'delta' => '25000.00',
            'salary_accrued' => '12500.00',
        ]);

        // Second order moved to second half, only indirect deal, so KPI should be 8
        $this->assertDatabaseHas('orders', [
            'id' => $secondOrderId,
            'kpi_percent' => '8.00',
            'delta' => '22000.00',
            'salary_accrued' => '11000.00',
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

        $orderId = (int) DB::table('orders')->insertGetId([
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

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'carrier_rate' => 3210.45,
        ]);

        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
        ]);

        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);
        $this->assertStringContainsString('"amount":3210.45', $contractorsCosts);
        $this->assertStringContainsString('"contractor_id":'.$carrierId, $contractorsCosts);
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

        $orderId = (int) DB::table('orders')->insertGetId([
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

        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);
        $this->assertStringContainsString('"payment_form":"vat"', $contractorsCosts);
    }

    public function test_inline_update_rate_still_works_when_financial_terms_table_is_missing(): void
    {
        Schema::dropIfExists('financial_terms');

        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('INLINE-NO-FIN-TERMS', $manager->id);

        $response = $this->actingAs($manager)->patch(route('orders.inline-update', $orderId), [
            'field' => 'customer_rate',
            'value' => 1999.99,
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_rate' => 1999.99,
        ]);
        $this->assertFalse(Schema::hasTable('financial_terms'));
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
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'track_number_customer' => null,
        ]);
    }

    public function test_manager_cannot_delete_loaded_order(): void
    {
        $managerRoleId = $this->createRole('manager', ['orders' => 'own']);
        $manager = User::factory()->create();

        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);
        $manager->role_id = $managerRoleId;

        $orderId = $this->createOrder('LOCKED-ORDER', $manager->id, '2026-04-02');

        $response = $this->actingAs($manager)->delete(route('orders.destroy', $orderId));

        $response->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'deleted_at' => null]);
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

        $orderId = (int) DB::table('orders')->insertGetId([
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

        $orderId = (int) DB::table('orders')->insertGetId([
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

        $orderId = (int) DB::table('orders')->insertGetId([
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

    private function createOrder(string $orderNumber, int $managerId, ?string $loadingDate = null): int
    {
        return (int) DB::table('orders')->insertGetId([
            'order_number' => $orderNumber,
            'manager_id' => $managerId,
            'loading_date' => $loadingDate,
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
    }
}
