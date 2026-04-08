<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerformerPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('leg_costs');
        Schema::dropIfExists('leg_contractor_assignments');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('order_legs');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('financial_terms');
        Schema::dropIfExists('cargo_leg');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
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
            $table->string('type')->default('customer');
            $table->string('name');
            $table->string('inn', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_own_company')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->string('company_code', 10)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->date('order_date')->nullable();
            $table->date('loading_date')->nullable();
            $table->date('unloading_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('own_company_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('customer_payment_form', 50)->nullable();
            $table->string('customer_payment_term', 50)->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->string('carrier_payment_term', 50)->nullable();
            $table->decimal('additional_expenses', 12, 2)->default(0);
            $table->decimal('insurance', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('kpi_percent', 5, 2)->nullable();
            $table->decimal('delta', 12, 2)->nullable();
            $table->decimal('salary_accrued', 12, 2)->default(0);
            $table->string('status', 50)->default('new');
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('performers')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('order_legs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->integer('sequence')->default(1);
            $table->string('type')->default('transport');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->string('type');
            $table->integer('sequence')->default(1);
            $table->string('address')->nullable();
            $table->json('normalized_data')->nullable();
            $table->string('kladr_id')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_contact')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_contact')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('cargo_leg', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cargo_id');
            $table->unsignedBigInteger('order_leg_id');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->string('status')->default('planned');
            $table->timestamps();
        });

        Schema::create('financial_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->decimal('client_price', 12, 2)->nullable();
            $table->string('client_currency', 3)->default('RUB');
            $table->string('client_payment_terms')->nullable();
            $table->json('contractors_costs')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('margin', 12, 2)->default(0);
            $table->json('additional_costs')->nullable();
            $table->timestamps();
        });

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type');
            $table->string('number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('generated_pdf_path')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('leg_contractor_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->unsignedBigInteger('contractor_id');
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('status', 50)->default('confirmed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('leg_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->string('payment_form', 50)->nullable();
            $table->json('payment_schedule')->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->unsignedBigInteger('leg_contractor_assignment_id')->nullable();
            $table->timestamps();
        });
    }

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

        $orderId = (int) DB::table('orders')->insertGetId([
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

        $orderId = (int) DB::table('orders')->insertGetId([
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
