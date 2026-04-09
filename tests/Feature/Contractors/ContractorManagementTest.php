<?php

namespace Tests\Feature\Contractors;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractorManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('contractor_documents');
        Schema::dropIfExists('contractor_interactions');
        Schema::dropIfExists('contractor_contacts');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('contractor_activity_types');
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
            $table->string('full_name')->nullable();
            $table->text('short_description')->nullable();
            $table->string('inn', 20)->nullable();
            $table->string('kpp', 20)->nullable();
            $table->string('ogrn', 20)->nullable();
            $table->string('okpo', 20)->nullable();
            $table->string('legal_form')->nullable();
            $table->string('legal_address')->nullable();
            $table->string('actual_address')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_person_phone', 50)->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_position')->nullable();
            $table->string('signer_name_nominative')->nullable();
            $table->string('signer_name_prepositional')->nullable();
            $table->string('signer_authority_basis')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bik', 9)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('correspondent_account', 20)->nullable();
            $table->json('ati_profiles')->nullable();
            $table->string('ati_id')->nullable();
            $table->json('transport_requirements')->nullable();
            $table->json('specializations')->nullable();
            $table->json('activity_types')->nullable();
            $table->decimal('rating', 5, 2)->nullable();
            $table->unsignedInteger('completed_orders')->default(0);
            $table->json('metadata')->nullable();
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

        Schema::create('contractor_activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->string('status')->nullable();
            $table->date('order_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('party');
            $table->string('type')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contractor_id');
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contractor_id');
            $table->timestamp('contacted_at')->nullable();
            $table->string('channel', 50)->nullable();
            $table->string('subject')->nullable();
            $table->text('summary')->nullable();
            $table->string('result')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contractor_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contractor_id');
            $table->string('type')->nullable();
            $table->string('title');
            $table->string('number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_admin_can_open_contractors_page(): void
    {
        $admin = $this->createAdminUser();

        DB::table('contractors')->insert([
            'type' => 'customer',
            'name' => 'ООО Тест',
            'inn' => '1234567890',
            'debt_limit' => 250000,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => true,
            'default_customer_payment_schedule' => json_encode([
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 7,
                'postpayment_mode' => 'ottn',
            ], JSON_THROW_ON_ERROR),
            'default_customer_payment_form' => 'vat',
            'default_customer_payment_term' => '7 дн OTTN',
            'default_carrier_payment_schedule' => json_encode([
                'has_prepayment' => true,
                'prepayment_ratio' => 50,
                'prepayment_days' => 1,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 5,
                'postpayment_mode' => 'ottn',
            ], JSON_THROW_ON_ERROR),
            'default_carrier_payment_form' => 'no_vat',
            'default_carrier_payment_term' => '50/50, 1 дн FTTN / 5 дн OTTN',
            'cooperation_terms_notes' => 'Работаем по заявкам и ЭДО.',
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('contractors.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contractors/Index')
            ->has('contractors', 1)
            ->has('legalFormOptions')
            ->where('legalFormOptions.0.label', 'ООО')
        );
    }

    public function test_admin_can_create_contractor_with_nested_data(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->post(route('contractors.store'), [
            'type' => 'both',
            'name' => 'ООО Логистика Плюс',
            'full_name' => 'Общество с ограниченной ответственностью Логистика Плюс',
            'inn' => '1234567890',
            'kpp' => '123456789',
            'ogrn' => '1234567890123',
            'legal_form' => 'ooo',
            'legal_address' => 'г. Самара, ул. Полевая, д. 10',
            'actual_address' => 'г. Самара, ул. Полевая, д. 10',
            'phone' => '+7 999 111-22-33',
            'email' => 'office@example.com',
            'specializations' => ['FTL', 'Реф'],
            'activity_types' => ['Экспедирование', 'Международные перевозки'],
            'transport_requirements' => ['Страховка', 'GPS'],
            'short_description' => 'Международная логистика и проектные перевозки.',
            'is_active' => true,
            'is_verified' => true,
            'is_own_company' => true,
            'debt_limit' => 250000,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => true,
            'default_customer_payment_form' => 'vat',
            'default_customer_payment_term' => '7 дн OTTN',
            'default_carrier_payment_form' => 'no_vat',
            'default_carrier_payment_term' => '50/50, 1 дн FTTN / 5 дн OTTN',
            'cooperation_terms_notes' => 'Работаем по заявкам и ЭДО.',
            'contacts' => [
                [
                    'full_name' => 'Иван Петров',
                    'position' => 'Логист',
                    'phone' => '+7 999 123-45-67',
                    'email' => 'ivan@example.com',
                    'is_primary' => true,
                    'notes' => 'Основной контакт',
                ],
            ],
            'interactions' => [
                [
                    'contacted_at' => now()->toDateTimeString(),
                    'channel' => 'phone',
                    'subject' => 'Первичный звонок',
                    'summary' => 'Обсудили условия работы',
                    'result' => 'Ожидает договор',
                ],
            ],
            'documents' => [
                [
                    'type' => 'contract',
                    'title' => 'Договор поставки',
                    'number' => '42',
                    'document_date' => now()->toDateString(),
                    'status' => 'signed',
                    'notes' => 'Оригинал у менеджера',
                ],
            ],
        ]);

        $contractorId = DB::table('contractors')->value('id');

        $response->assertRedirect(route('contractors.show', $contractorId));
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'name' => 'ООО Логистика Плюс',
            'created_by' => $admin->id,
            'is_own_company' => true,
            'debt_limit' => '250000.00',
            'stop_on_limit' => true,
            'default_customer_payment_form' => 'vat',
            'short_description' => 'Международная логистика и проектные перевозки.',
        ]);
        $this->assertSame(
            ['Экспедирование', 'Международные перевозки'],
            json_decode((string) DB::table('contractors')->where('id', $contractorId)->value('activity_types'), true, 512, JSON_THROW_ON_ERROR)
        );
        $this->assertDatabaseHas('contractor_contacts', [
            'contractor_id' => $contractorId,
            'full_name' => 'Иван Петров',
        ]);
        $this->assertDatabaseHas('contractor_documents', [
            'contractor_id' => $contractorId,
            'title' => 'Договор поставки',
        ]);
    }

    public function test_admin_can_store_global_activity_type_reference(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson(route('contractors.activity-types.store'), [
            'name' => 'Экспедирование',
        ]);

        $response->assertCreated()
            ->assertJsonPath('activityType.name', 'Экспедирование');

        $this->assertDatabaseHas('contractor_activity_types', [
            'name' => 'Экспедирование',
        ]);

        $pageResponse = $this->actingAs($admin)->get(route('contractors.index'));

        $pageResponse->assertInertia(fn (Assert $page) => $page
            ->component('Contractors/Index')
            ->where('activityTypeOptions.0', 'Экспедирование')
        );
    }

    public function test_admin_can_update_contractor(): void
    {
        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Старое название',
            'inn' => '1234567890',
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('contractors.update', $contractorId), [
            'type' => 'carrier',
            'name' => 'ООО Новое название',
            'full_name' => 'ООО Новое название',
            'inn' => '1234567890',
            'kpp' => '',
            'ogrn' => '',
            'okpo' => '',
            'legal_form' => 'ooo',
            'legal_address' => '',
            'actual_address' => '',
            'postal_address' => '',
            'phone' => '+7 999 000-00-00',
            'email' => 'new@example.com',
            'website' => '',
            'contact_person' => '',
            'contact_person_phone' => '',
            'contact_person_email' => '',
            'contact_person_position' => '',
            'signer_name_nominative' => 'Иванов Иван Иванович',
            'signer_name_prepositional' => 'Иванове Иване Ивановиче',
            'signer_authority_basis' => 'Устав',
            'bank_name' => '',
            'bik' => '',
            'account_number' => '',
            'correspondent_account' => '',
            'ati_id' => '',
            'specializations' => ['Тент'],
            'activity_types' => ['Внутрироссийские перевозки'],
            'transport_requirements' => [],
            'short_description' => 'Работает по РФ.',
            'debt_limit' => 150000,
            'debt_limit_currency' => 'USD',
            'stop_on_limit' => true,
            'default_customer_payment_form' => 'no_vat',
            'default_customer_payment_term' => '5 дн OTTN',
            'default_carrier_payment_schedule' => [
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 100,
                'postpayment_mode' => 'fttn',
            ],
            'default_carrier_payment_form' => 'cash',
            'default_carrier_payment_term' => '100 дн FTTN',
            'cooperation_terms_notes' => 'Только по предоплате.',
            'debt_limit' => 150000,
            'debt_limit_currency' => 'USD',
            'stop_on_limit' => true,
            'default_customer_payment_form' => 'no_vat',
            'default_customer_payment_term' => '5 дн OTTN',
            'default_carrier_payment_form' => 'cash',
            'default_carrier_payment_term' => '100 дн FTTN',
            'cooperation_terms_notes' => 'Только по предоплате.',
            'is_active' => false,
            'is_verified' => false,
            'is_own_company' => true,
            'debt_limit' => 150000,
            'debt_limit_currency' => 'USD',
            'stop_on_limit' => true,
            'default_customer_payment_schedule' => [
                'has_prepayment' => false,
                'prepayment_ratio' => 50,
                'prepayment_days' => 0,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 5,
                'postpayment_mode' => 'ottn',
            ],
            'default_customer_payment_form' => 'no_vat',
            'default_customer_payment_term' => '5 дн OTTN',
            'default_carrier_payment_form' => 'cash',
            'default_carrier_payment_term' => '100 дн FTTN',
            'cooperation_terms_notes' => 'Только по предоплате.',
            'contacts' => [],
            'interactions' => [],
            'documents' => [],
        ]);

        $response->assertRedirect(route('contractors.show', $contractorId));
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'type' => 'carrier',
            'name' => 'ООО Новое название',
            'is_active' => false,
            'is_own_company' => true,
            'debt_limit' => '150000.00',
            'debt_limit_currency' => 'USD',
            'stop_on_limit' => true,
            'default_carrier_payment_form' => 'cash',
            'short_description' => 'Работает по РФ.',
            'signer_name_nominative' => 'Иванов Иван Иванович',
            'signer_name_prepositional' => 'Иванове Иване Ивановиче',
            'signer_authority_basis' => 'Устав',
            'updated_by' => $admin->id,
        ]);
        $this->assertSame(
            ['Внутрироссийские перевозки'],
            json_decode((string) DB::table('contractors')->where('id', $contractorId)->value('activity_types'), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function test_cannot_delete_contractor_with_orders(): void
    {
        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО С заказом',
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'order_number' => 'ORD-001',
            'status' => 'new',
            'customer_id' => $contractorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('contractors.destroy', $contractorId));

        $response->assertStatus(422);
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
        ]);
    }

    public function test_party_suggestions_proxy_returns_dadata_payload(): void
    {
        $admin = $this->createAdminUser();

        Config::set('services.dadata.token', 'test-token');
        Config::set('services.dadata.secret', 'test-secret');

        Http::fake([
            'https://suggestions.dadata.ru/*' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'ООО Логистика Плюс',
                        'data' => [
                            'inn' => '1234567890',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.suggest-party', [
            'query' => '1234567890',
        ]));

        $response->assertOk();
        $response->assertJsonPath('suggestions.0.value', 'ООО Логистика Плюс');
    }

    public function test_admin_can_open_contractors_page_without_nested_tables(): void
    {
        Schema::dropIfExists('contractor_documents');
        Schema::dropIfExists('contractor_interactions');
        Schema::dropIfExists('contractor_contacts');
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('is_own_company');
        });

        $admin = $this->createAdminUser();

        DB::table('contractors')->insert([
            'type' => 'customer',
            'name' => 'Compatibility contractor',
            'inn' => '1234567890',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('contractors.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contractors/Index')
            ->where('contractors.0.contacts_count', 0)
            ->where('contractors.0.is_own_company', false)
        );
    }

    public function test_selected_contractor_includes_current_debt_and_related_order_documents(): void
    {
        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type');
            $table->string('document_group')->nullable();
            $table->string('number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('original_name')->nullable();
            $table->string('status')->nullable();
            $table->string('signature_status')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'debt_limit' => 100000,
            'stop_on_limit' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-500',
            'status' => 'documents',
            'order_date' => '2026-04-03',
            'customer_rate' => 80000,
            'carrier_rate' => 55000,
            'customer_id' => $contractorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 110000,
            'status' => 'overdue',
            'planned_date' => '2026-04-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_documents')->insert([
            'order_id' => $orderId,
            'type' => 'request',
            'document_group' => 'request',
            'number' => 'REQ-500',
            'document_date' => '2026-04-03',
            'original_name' => 'request.pdf',
            'status' => 'sent',
            'signature_status' => 'signed_internal',
            'file_path' => 'order-documents/request.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('contractors.show', $contractorId));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedContractor.current_debt', 110000)
            ->where('selectedContractor.debt_limit_reached', true)
            ->where('selectedContractor.order_documents.0.order_number', 'ORD-500')
            ->where('selectedContractor.order_documents.0.signature_status', 'signed_internal')
        );
    }

    public function test_contractors_show_preserves_list_page_from_query_string(): void
    {
        $admin = $this->createAdminUser();

        for ($i = 1; $i <= 11; $i++) {
            DB::table('contractors')->insert([
                'type' => 'customer',
                'name' => sprintf('ООО Тест %02d', $i),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $eleventhId = (int) DB::table('contractors')
            ->orderBy('name')
            ->skip(10)
            ->value('id');

        $response = $this->actingAs($admin)->get(route('contractors.show', [
            'contractor' => $eleventhId,
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('pagination.current_page', 2)
            ->where('selectedContractor.id', $eleventhId)
        );
    }

    public function test_update_redirect_preserves_list_context(): void
    {
        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Контекст',
            'inn' => '1234567890',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('contractors.update', [
            'contractor' => $contractorId,
            'page' => 2,
            'search' => 'Контекст',
            'type' => 'customer',
        ]), [
            'type' => 'customer',
            'name' => 'ООО Контекст Обновлён',
            'full_name' => 'ООО Контекст Обновлён',
            'inn' => '1234567890',
            'kpp' => '',
            'ogrn' => '',
            'okpo' => '',
            'legal_form' => 'ooo',
            'legal_address' => '',
            'actual_address' => '',
            'postal_address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'contact_person' => '',
            'contact_person_phone' => '',
            'contact_person_email' => '',
            'contact_person_position' => '',
            'signer_name_nominative' => '',
            'signer_name_prepositional' => '',
            'signer_authority_basis' => '',
            'bank_name' => '',
            'bik' => '',
            'account_number' => '',
            'correspondent_account' => '',
            'ati_id' => '',
            'specializations' => [],
            'activity_types' => [],
            'transport_requirements' => [],
            'short_description' => '',
            'debt_limit' => null,
            'debt_limit_currency' => 'RUB',
            'stop_on_limit' => false,
            'default_customer_payment_form' => '',
            'default_customer_payment_term' => '',
            'default_carrier_payment_form' => '',
            'default_carrier_payment_term' => '',
            'cooperation_terms_notes' => '',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'contacts' => [],
            'interactions' => [],
            'documents' => [],
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith(route('contractors.show', $contractorId), $location);

        $queryString = (string) parse_url($location, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        $this->assertSame('2', (string) ($queryParams['page'] ?? null));
        $this->assertSame('Контекст', $queryParams['search'] ?? null);
        $this->assertSame('customer', $queryParams['type'] ?? null);
    }

    private function createAdminUser(): User
    {
        $adminRoleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'visibility_areas' => json_encode(['contractors'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $adminRoleId,
        ]);
    }
}
