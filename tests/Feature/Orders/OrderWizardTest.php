<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('financial_terms');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('cargo_leg');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('order_legs');
        Schema::dropIfExists('salary_coefficients');
        Schema::dropIfExists('kpi_settings');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('columns_config')->nullable();
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
            $table->string('kpp', 20)->nullable();
            $table->string('legal_address')->nullable();
            $table->string('actual_address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->decimal('debt_limit', 12, 2)->nullable();
            $table->string('debt_limit_currency', 3)->default('RUB');
            $table->boolean('stop_on_limit')->default(false);
            $table->string('default_customer_payment_form', 50)->nullable();
            $table->string('default_customer_payment_term')->nullable();
            $table->json('default_customer_payment_schedule')->nullable();
            $table->string('default_carrier_payment_form', 50)->nullable();
            $table->string('default_carrier_payment_term')->nullable();
            $table->json('default_carrier_payment_schedule')->nullable();
            $table->text('cooperation_terms_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_own_company')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
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
            $table->text('payment_terms')->nullable();
            $table->text('special_notes')->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->string('carrier_payment_term', 50)->nullable();
            $table->decimal('additional_expenses', 12, 2)->default(0);
            $table->decimal('insurance', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('kpi_percent', 5, 2)->nullable();
            $table->decimal('delta', 12, 2)->nullable();
            $table->decimal('salary_accrued', 12, 2)->default(0);
            $table->decimal('salary_paid', 12, 2)->default(0);
            $table->string('status', 50)->default('draft');
            $table->string('manual_status', 50)->nullable();
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('own_company_id')->nullable();
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->json('payment_statuses')->nullable();
            $table->json('performers')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->unsignedBigInteger('address_id')->nullable();
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
            $table->timestamps();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('volume', 10, 2)->nullable();
            $table->string('cargo_type')->nullable();
            $table->string('packing_type')->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->boolean('is_hazardous')->default(false);
            $table->string('hazard_class')->nullable();
            $table->string('hs_code')->nullable();
            $table->boolean('needs_temperature')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
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
            $table->timestamp('signed_at')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->json('metadata')->nullable();
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

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('party', ['customer', 'carrier']);
            $table->enum('type', ['prepayment', 'final']);
            $table->decimal('amount', 12, 2);
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('status_from')->nullable();
            $table->string('status_to');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_coefficients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id');
            $table->integer('base_salary')->default(0);
            $table->integer('bonus_percent')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_admin_can_open_order_wizard_create_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('orders.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->has('currencyOptions')
            ->has('orderStatusOptions')
            ->has('documentPartyOptions', 3)
            ->has('requiredDocumentRules', 5)
            ->has('requiredDocumentChecklist', 5)
            ->has('currentUser')
        );
    }

    public function test_admin_can_create_order_with_nested_data(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_settings')->insert([
            'key' => 'delta_bonus_multiplier',
            'value' => '1.30',
            'type' => 'float',
            'group' => 'delta',
            'description' => 'Multiplier',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_coefficients')->insert([
            'manager_id' => $admin->id,
            'base_salary' => 10000,
            'bonus_percent' => 10,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '1234567890',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ООО Наша Компания',
            'inn' => '9876543210',
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'inn' => '5555555555',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('orders.store'), [
            'status' => 'new',
            'own_company_id' => $ownCompanyId,
            'client_id' => $clientId,
            'order_date' => '2026-04-01',
            'order_number' => '',
            'special_notes' => 'Хрупкий груз',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Московское шоссе, 10',
                    'normalized_data' => ['city' => 'Самара', 'coordinates' => ['lat' => '53.2', 'lng' => '50.1']],
                    'planned_date' => '2026-04-02',
                    'contact_person' => 'Иван',
                    'contact_phone' => '+79990000000',
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'Казань, ул. Баумана, 1',
                    'normalized_data' => ['city' => 'Казань'],
                    'planned_date' => '2026-04-03',
                    'contact_person' => 'Петр',
                    'contact_phone' => '+79991111111',
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Бытовая техника',
                    'description' => 'Партия холодильников',
                    'weight_kg' => 1200,
                    'volume_m3' => 16.5,
                    'package_type' => 'pallet',
                    'package_count' => 10,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => '841810',
                    'cargo_type' => 'general',
                ],
            ],
            'financial_term' => [
                'client_price' => 120000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat',
                'client_payment_schedule' => [
                    'has_prepayment' => true,
                    'prepayment_ratio' => 30,
                    'prepayment_days' => 1,
                    'prepayment_mode' => 'fttn',
                    'postpayment_days' => 5,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 10,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 80000,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 7,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [
                    ['label' => 'Страхование', 'amount' => 5000, 'currency' => 'RUB'],
                ],
            ],
            'documents' => [
                [
                    'type' => 'request',
                    'party' => 'customer',
                    'requirement_key' => 'customer_request',
                    'number' => 'REQ-1',
                    'document_date' => '2026-04-01',
                    'status' => 'draft',
                    'template_id' => 5,
                    'file' => UploadedFile::fake()->create('request.pdf', 120, 'application/pdf'),
                ],
            ],
        ]);

        $orderId = DB::table('orders')->value('id');

        $response->assertRedirect(route('orders.edit', $orderId));
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_id' => $clientId,
            'own_company_id' => $ownCompanyId,
            'manager_id' => $admin->id,
            'status' => 'documents',
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'no_vat',
        ]);
        $this->assertDatabaseHas('route_points', [
            'address' => 'Самара, Московское шоссе, 10',
        ]);
        $this->assertDatabaseHas('cargos', [
            'order_id' => $orderId,
            'title' => 'Бытовая техника',
        ]);
        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => 120000,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'delta' => '23000.00',
            'salary_accrued' => '12300.00',
        ]);
        $financialTerm = DB::table('financial_terms')->where('order_id', $orderId)->first();
        $this->assertNotNull($financialTerm);
        $this->assertSame('30/70, 1 дн FTTN / 5 дн OTTN', $financialTerm->client_payment_terms);
        $this->assertStringContainsString('"payment_form":"no_vat"', (string) $financialTerm->contractors_costs);
        $this->assertDatabaseHas('order_documents', [
            'order_id' => $orderId,
            'number' => 'REQ-1',
        ]);
        $documentMetadata = DB::table('order_documents')
            ->where('order_id', $orderId)
            ->where('number', 'REQ-1')
            ->value('metadata');
        $this->assertIsString($documentMetadata);
        $this->assertStringContainsString('"party":"customer"', $documentMetadata);
        $this->assertStringContainsString('"requirement_key":"customer_request"', $documentMetadata);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $orderId,
            'status_to' => 'documents',
        ]);
    }

    public function test_admin_can_update_order_and_persist_contractor_costs(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'inn' => '1234567890',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Перевозчик',
            'inn' => '5555555555',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-2026-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'own_company_id' => null,
            'client_id' => $clientId,
            'order_date' => '2026-04-02',
            'order_number' => 'ORD-2026-001',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_custom', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Московское шоссе, 10',
                    'normalized_data' => [],
                    'planned_date' => '2026-04-02',
                    'actual_date' => null,
                    'contact_person' => null,
                    'contact_phone' => null,
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 150000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 5,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_custom',
                        'contractor_id' => $carrierId,
                        'amount' => 99000.50,
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
            ],
            'documents' => [],
        ]);

        $response->assertRedirect(route('orders.edit', $orderId));

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'carrier_rate' => '99000.50',
            'customer_rate' => '150000.00',
        ]);

        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => '150000.00',
        ]);

        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);
        $this->assertStringContainsString('"amount":99000.5', $contractorsCosts);
        $this->assertStringContainsString('"stage":"leg_custom"', $contractorsCosts);
    }

    public function test_edit_page_restores_contractor_costs_from_order_rate_when_financial_terms_row_is_missing(): void
    {
        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-RESTORE-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'carrier_id' => $carrierId,
            'customer_rate' => 150000,
            'carrier_rate' => 88000,
            'performers' => json_encode([
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_legs')->insert([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.financial_term.client_price', '150000.00')
            ->where('order.financial_term.contractors_costs.0.contractor_id', $carrierId)
            ->where('order.financial_term.contractors_costs.0.amount', 88000)
        );
    }

    public function test_edit_page_opens_with_cargos_linked_through_legs_and_legacy_order_columns_missing(): void
    {
        Schema::dropIfExists('financial_terms');
        Schema::table('cargos', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['own_company_id', 'payment_terms', 'special_notes', 'performers']);
        });

        $admin = $this->createAdminUser();

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Client',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-LEGACY-001',
            'company_code' => 'TST',
            'manager_id' => $admin->id,
            'order_date' => '2026-04-01',
            'status' => 'new',
            'customer_id' => $clientId,
            'carrier_id' => $carrierId,
            'customer_rate' => 150000,
            'carrier_rate' => 88000,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
            'type' => 'transport',
            'description' => 'leg_1',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cargoId = DB::table('cargos')->insertGetId([
            'title' => 'Legacy cargo',
            'description' => 'Linked via cargo_leg only',
            'weight' => 100,
            'volume' => 5,
            'cargo_type' => 'general',
            'packing_type' => 'pallet',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cargo_leg')->insert([
            'cargo_id' => $cargoId,
            'order_leg_id' => $legId,
            'quantity' => 1,
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.edit', $orderId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Wizard')
            ->where('order.cargo_items.0.name', 'Legacy cargo')
            ->where('order.financial_term.client_price', '150000.00')
            ->where('order.financial_term.contractors_costs.0.contractor_id', $carrierId)
            ->where('order.financial_term.contractors_costs.0.amount', 88000)
        );
    }

    public function test_order_create_page_exposes_contractor_credit_policy_and_default_terms(): void
    {
        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'debt_limit' => 125000,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => true,
            'default_customer_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 7,
                'postpayment_mode' => 'ottn',
            ], JSON_UNESCAPED_UNICODE),
            'default_customer_payment_form' => 'vat',
            'default_customer_payment_term' => '7 дн OTTN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-DEBT-001',
            'status' => 'payment',
            'customer_id' => $contractorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 130000,
            'planned_date' => '2026-04-03',
            'status' => 'overdue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('orders.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('contractors.0.default_customer_payment_form', 'vat')
            ->where('contractors.0.default_customer_payment_term', '7 дн OTTN')
            ->where('contractors.0.default_customer_payment_schedule.postpayment_days', 7)
            ->where('contractors.0.default_customer_payment_schedule.postpayment_mode', 'ottn')
            ->where('contractors.0.current_debt', 130000)
            ->where('contractors.0.debt_limit_reached', true)
        );
    }

    public function test_order_creation_is_blocked_when_customer_debt_limit_is_reached(): void
    {
        $admin = $this->createAdminUser();

        DB::table('kpi_settings')->insert([
            'key' => 'delta_bonus_multiplier',
            'value' => '1.00',
            'type' => 'float',
            'group' => 'delta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'Blocked Client',
            'debt_limit' => 100000,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => true,
            'default_customer_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 7,
                'postpayment_mode' => 'ottn',
            ], JSON_UNESCAPED_UNICODE),
            'default_customer_payment_form' => 'vat',
            'default_customer_payment_term' => '7 дн OTTN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Carrier',
            'default_carrier_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 5,
                'postpayment_mode' => 'ottn',
            ], JSON_UNESCAPED_UNICODE),
            'default_carrier_payment_form' => 'no_vat',
            'default_carrier_payment_term' => '5 дн OTTN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacyOrderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-LEGACY-DEBT',
            'status' => 'payment',
            'customer_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $legacyOrderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'planned_date' => '2026-04-02',
            'status' => 'overdue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->from(route('orders.create'))->post(route('orders.store'), [
            'status' => 'new',
            'client_id' => $clientId,
            'order_date' => '2026-04-03',
            'order_number' => '',
            'special_notes' => '',
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => $carrierId],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'address' => 'Самара', 'normalized_data' => []],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 1000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 7,
                    'postpayment_mode' => 'ottn',
                ],
                'kpi_percent' => 0,
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrierId,
                        'amount' => 500,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 5,
                            'postpayment_mode' => 'ottn',
                        ],
                    ],
                ],
                'additional_costs' => [],
            ],
            'documents' => [],
        ]);

        $response->assertRedirect(route('orders.create'));
        $response->assertSessionHasErrors('client_id');
        $this->assertDatabaseCount('orders', 1);
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

        $user = User::factory()->create();

        DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
        $user->role_id = $roleId;

        return $user;
    }
}
