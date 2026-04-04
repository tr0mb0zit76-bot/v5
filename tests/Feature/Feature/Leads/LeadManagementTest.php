<?php

namespace Tests\Feature\Feature\Leads;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('financial_terms');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('cargo_leg');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('order_legs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('lead_offers');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_cargo_items');
        Schema::dropIfExists('lead_route_points');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('salary_coefficients');
        Schema::dropIfExists('kpi_settings');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
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
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_own_company')->default(false);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->string('source', 100)->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('transport_type', 100)->nullable();
            $table->string('loading_location')->nullable();
            $table->string('unloading_location')->nullable();
            $table->date('planned_shipping_date')->nullable();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->string('target_currency', 3)->default('RUB');
            $table->decimal('calculated_cost', 12, 2)->nullable();
            $table->decimal('expected_margin', 12, 2)->nullable();
            $table->timestamp('proposal_sent_at')->nullable();
            $table->timestamp('next_contact_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->json('lead_qualification')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('type', 50);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('address', 500);
            $table->json('normalized_data')->nullable();
            $table->date('planned_date')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_cargo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->decimal('volume_m3', 10, 2)->nullable();
            $table->string('package_type', 50)->nullable();
            $table->unsignedInteger('package_count')->nullable();
            $table->boolean('dangerous_goods')->default(false);
            $table->string('dangerous_class', 10)->nullable();
            $table->string('hs_code', 50)->nullable();
            $table->string('cargo_type', 50)->default('general');
            $table->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('type', 50)->default('note');
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('status', 50)->default('draft');
            $table->string('number')->nullable();
            $table->date('offer_date')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->json('payload')->nullable();
            $table->string('generated_file_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->string('customer_payment_form', 50)->nullable();
            $table->string('customer_payment_term', 50)->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('special_notes')->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->string('carrier_payment_term', 50)->nullable();
            $table->decimal('additional_expenses', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('kpi_percent', 5, 2)->nullable();
            $table->decimal('delta', 12, 2)->nullable();
            $table->decimal('salary_accrued', 12, 2)->nullable();
            $table->decimal('salary_paid', 12, 2)->default(0);
            $table->string('status', 50)->nullable();
            $table->unsignedBigInteger('status_updated_by')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('own_company_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->json('performers')->nullable();
            $table->json('metadata')->nullable();
            $table->json('payment_statuses')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('order_legs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('sequence');
            $table->string('type', 50);
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->unsignedBigInteger('address_id')->nullable();
            $table->string('type', 50);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('address', 500)->nullable();
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
            $table->unsignedBigInteger('order_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('volume', 10, 2)->nullable();
            $table->string('cargo_type')->nullable();
            $table->string('packing_type')->nullable();
            $table->integer('package_count')->nullable();
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
            $table->string('status', 50)->default('planned');
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
            $table->string('status', 50)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->decimal('client_price', 12, 2)->nullable();
            $table->string('client_currency', 3)->nullable();
            $table->string('client_payment_terms')->nullable();
            $table->json('contractors_costs')->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->decimal('margin', 12, 2)->nullable();
            $table->json('additional_costs')->nullable();
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
            $table->string('type')->nullable();
            $table->string('group')->nullable();
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

    public function test_manager_sees_only_own_leads(): void
    {
        $manager = $this->createUserWithRole('manager');
        $otherManager = $this->createUserWithRole('manager');

        Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'Свой лид',
        ]);

        Lead::factory()->create([
            'responsible_id' => $otherManager->id,
            'title' => 'Чужой лид',
        ]);

        $response = $this->actingAs($manager)->get(route('leads.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->has('leads', 1)
            ->where('leads.0.title', 'Свой лид')
        );
    }

    public function test_manager_can_create_lead_with_nested_data(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $response = $this->actingAs($manager)->post(route('leads.store'), [
            'status' => 'new',
            'source' => 'inbound',
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Лид на перевозку оборудования',
            'description' => 'Нужно срочно просчитать рейс',
            'transport_type' => 'ftl',
            'loading_location' => 'Самара',
            'unloading_location' => 'Казань',
            'planned_shipping_date' => now()->addDays(5)->toDateString(),
            'target_price' => 150000,
            'target_currency' => 'RUB',
            'qualification' => [
                'need' => 'FTL',
                'timeline' => '5 дней',
                'authority' => 'Директор',
                'budget' => 'До 150 000',
            ],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Самара, Заводская 1',
                    'normalized_data' => [],
                    'planned_date' => now()->addDays(5)->toDateString(),
                ],
            ],
            'cargo_items' => [
                [
                    'name' => 'Оборудование',
                    'description' => 'Станки',
                    'weight_kg' => 1200,
                    'volume_m3' => 9.5,
                    'package_type' => 'pallet',
                    'package_count' => 4,
                    'dangerous_goods' => false,
                    'dangerous_class' => null,
                    'hs_code' => null,
                    'cargo_type' => 'general',
                ],
            ],
            'activities' => [
                [
                    'type' => 'call',
                    'subject' => 'Первичный звонок',
                    'content' => 'Уточнили сроки',
                    'next_action_at' => now()->addDay()->format('Y-m-d H:i:s'),
                ],
            ],
        ]);

        $leadId = DB::table('leads')->value('id');

        $response->assertRedirect(route('leads.show', $leadId));
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'title' => 'Лид на перевозку оборудования',
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('lead_route_points', [
            'lead_id' => $leadId,
            'address' => 'Самара, Заводская 1',
        ]);
        $this->assertDatabaseHas('lead_cargo_items', [
            'lead_id' => $leadId,
            'name' => 'Оборудование',
        ]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $leadId,
            'subject' => 'Первичный звонок',
        ]);
    }

    public function test_manager_can_prepare_commercial_offer_for_lead(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'КП для клиента',
            'target_price' => 180000,
            'target_currency' => 'RUB',
        ]);

        $response = $this->actingAs($manager)->post(route('leads.proposal', $lead));

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertDatabaseHas('lead_offers', [
            'lead_id' => $lead->id,
            'status' => 'prepared',
            'number' => 'КП-'.$lead->number,
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'proposal_ready',
        ]);
    }

    public function test_manager_opens_lead_card_on_separate_page(): void
    {
        $manager = $this->createUserWithRole('manager');
        $lead = Lead::factory()->create([
            'responsible_id' => $manager->id,
            'title' => 'Отдельная карточка лида',
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Wizard')
            ->where('selectedLead.id', $lead->id)
            ->where('selectedLead.title', 'Отдельная карточка лида')
        );
    }

    public function test_manager_can_convert_lead_into_order(): void
    {
        $manager = $this->createUserWithRole('manager');
        $contractorId = $this->createContractor();

        $lead = Lead::factory()->create([
            'counterparty_id' => $contractorId,
            'responsible_id' => $manager->id,
            'title' => 'Конвертация в заказ',
            'target_price' => 210000,
            'target_currency' => 'RUB',
        ]);

        DB::table('lead_route_points')->insert([
            'lead_id' => $lead->id,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Москва, Склад 10',
            'normalized_data' => json_encode([], JSON_THROW_ON_ERROR),
            'planned_date' => now()->addDays(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('leads.convert', $lead));

        $orderId = DB::table('orders')->where('lead_id', $lead->id)->value('id');

        $this->assertNotNull($orderId);
        $response->assertRedirect(route('orders.edit', $orderId));
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'lead_id' => $lead->id,
            'customer_id' => $contractorId,
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'won',
        ]);
    }

    public function test_index_returns_feature_unavailable_when_lead_tables_are_missing(): void
    {
        Schema::dropIfExists('lead_offers');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_cargo_items');
        Schema::dropIfExists('lead_route_points');
        Schema::dropIfExists('leads');

        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($manager)->get(route('leads.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('featureUnavailable', true)
            ->has('leads', 0)
        );
    }

    private function createUserWithRole(string $roleName): User
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $roleName,
                'display_name' => ucfirst($roleName),
                'visibility_areas' => json_encode(['dashboard', 'leads', 'orders'], JSON_THROW_ON_ERROR),
                'visibility_scopes' => json_encode([
                    'leads' => $roleName === 'manager' ? 'own' : 'all',
                    'orders' => $roleName === 'manager' ? 'own' : 'all',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }

    private function createContractor(): int
    {
        return (int) DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'is_active' => true,
            'is_verified' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
