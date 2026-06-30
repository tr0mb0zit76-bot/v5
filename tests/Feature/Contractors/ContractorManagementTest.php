<?php

namespace Tests\Feature\Contractors;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractorManagementTest extends TestCase
{
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
            ->has('contractorColumns')
            ->where('contractorColumns', fn ($columns) => collect($columns)->contains(
                fn ($column) => ($column['field'] ?? null) === 'name'
            ))
            ->where('contractors.0.status_text', 'Пауза в работе')
            ->where('contractors.0.primary_contact', '—')
            ->has('legalFormOptions')
            ->where('legalFormOptions.0.label', 'ООО')
            ->has('paymentFormOptions')
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
            'is_non_resident' => true,
            'non_resident_corr_bank_name' => 'Bank of China',
            'non_resident_corr_bank_swift' => 'BKCHCNBJ',
            'non_resident_corr_settlement_account' => '11122233344455566',
            'non_resident_corr_bank_account' => '99887766554433221100',
            'cnaps_code' => '123456789012',
            'bank_accounts' => [
                [
                    'label' => 'Основной RUB',
                    'country_code' => 'RU',
                    'currency' => 'RUB',
                    'bank_name' => 'ПАО Сбербанк',
                    'bik' => '044525225',
                    'account_number' => '40702810900000000001',
                    'correspondent_account' => '30101810400000000225',
                    'swift' => 'SABRRUMM',
                    'is_primary' => true,
                ],
                [
                    'label' => 'Нерезидент USD',
                    'country_code' => 'KZ',
                    'currency' => 'USD',
                    'bank_name' => 'Halyk Bank',
                    'account_number' => '12345678901234567890',
                    'correspondent_account' => '40111122233344455566',
                    'swift' => 'HSBKKZKX',
                    'iban' => 'KZ86125KZT5004100100',
                    'is_primary' => false,
                ],
            ],
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
                    'file' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $contractorId = (int) DB::table('contractors')->where('name', 'ООО Логистика Плюс')->value('id');
        $this->assertNotSame(0, $contractorId);

        $response->assertRedirect(route('contractors.show', [
            'contractor' => $contractorId,
        ]));
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'name' => 'ООО Логистика Плюс',
            'created_by' => $admin->id,
            'is_own_company' => true,
            'is_non_resident' => true,
            'non_resident_corr_bank_name' => 'Bank of China',
            'non_resident_corr_bank_swift' => 'BKCHCNBJ',
            'non_resident_corr_settlement_account' => '11122233344455566',
            'non_resident_corr_bank_account' => '99887766554433221100',
            'cnaps_code' => '123456789012',
            'debt_limit' => '250000.00',
            'stop_on_limit' => false,
            'default_customer_payment_form' => 'vat',
            'short_description' => 'Международная логистика и проектные перевозки.',
        ]);
        $storedBankAccounts = json_decode((string) DB::table('contractors')->where('id', $contractorId)->value('bank_accounts'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $storedBankAccounts);
        $this->assertSame('ПАО Сбербанк', $storedBankAccounts[0]['bank_name']);
        $this->assertTrue((bool) ($storedBankAccounts[0]['is_primary'] ?? false));
        $this->assertSame('40111122233344455566', $storedBankAccounts[1]['correspondent_account'] ?? null);
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
            'is_own_company' => false,
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

        $response->assertRedirect(route('contractors.show', [
            'contractor' => $contractorId,
        ]));
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'type' => 'carrier',
            'name' => 'ООО Новое название',
            'is_active' => false,
            'is_own_company' => false,
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

    public function test_update_contractor_persists_owner_id(): void
    {
        $admin = $this->createAdminUser();
        $newOwner = User::factory()->create([
            'role_id' => $admin->role_id,
        ]);

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Владелец тест',
            'inn' => '1234509876',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'owner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('contractors.update', $contractorId), [
            'type' => 'customer',
            'name' => 'ООО Владелец тест',
            'full_name' => '',
            'inn' => '1234509876',
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
            'owner_id' => $newOwner->id,
            'contacts' => [],
            'interactions' => [],
            'documents' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'owner_id' => $newOwner->id,
        ]);
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

    public function test_bank_suggestions_proxy_returns_dadata_payload(): void
    {
        $admin = $this->createAdminUser();

        Config::set('services.dadata.token', 'test-token');
        Config::set('services.dadata.secret', 'test-secret');

        Http::fake([
            'https://suggestions.dadata.ru/*' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'ПАО Сбербанк',
                        'data' => [
                            'bic' => '044525225',
                            'correspondent_account' => '30101810400000000225',
                            'swift' => 'SABRRUMM',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.suggest-bank', [
            'bik' => '044525225',
        ]));

        $response->assertOk();
        $response->assertJsonPath('suggestions.0.value', 'ПАО Сбербанк');
    }

    public function test_admin_can_open_contractors_page_without_nested_tables(): void
    {
        $this->markTestSkipped('Legacy DDL (drop nested tables / is_own_company) несовместим с RefreshDatabase на u_tromb.');
    }

    public function test_selected_contractor_includes_current_debt_and_related_order_documents(): void
    {
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

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-500',
            'status' => 'documents',
            'order_date' => '2026-04-03',
            'customer_rate' => 80000,
            'carrier_rate' => 55000,
            'customer_id' => $contractorId,
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

        $response = $this->actingAs($admin)->get(route('contractors.show', ['contractor' => $contractorId]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedContractor.current_debt', 110000)
            ->where('selectedContractor.debt_limit_reached', true)
            ->where('selectedContractor.order_documents.0.order_number', 'ORD-500')
            ->where('selectedContractor.order_documents.0.signature_status', 'signed_internal')
        );
    }

    public function test_contractors_show_ignores_legacy_page_query_string_with_virtual_scroll(): void
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
            ->where('pagination.current_page', 1)
            ->where('pagination.total', 11)
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

        $this->assertStringStartsWith(route('contractors.show', ['contractor' => $contractorId]), $location);

        $queryString = (string) parse_url($location, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        $this->assertSame('2', (string) ($queryParams['page'] ?? null));
        $this->assertSame('Контекст', $queryParams['search'] ?? null);
        $this->assertSame('customer', $queryParams['type'] ?? null);
    }

    public function test_contractor_search_with_type_carrier_includes_type_both(): void
    {
        $admin = $this->createAdminUser();

        $bothId = DB::table('contractors')->insertGetId([
            'type' => 'both',
            'name' => 'ИП Универсал Both',
            'full_name' => null,
            'inn' => null,
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownCompanyId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Универсал Своя компания',
            'full_name' => null,
            'inn' => null,
            'is_active' => true,
            'is_own_company' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Универсал Только заказчик',
            'full_name' => null,
            'inn' => null,
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.search', [
            'type' => 'carrier',
            'q' => 'Универсал',
        ]));

        $response->assertOk();
        $ids = collect($response->json('contractors'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertContains($bothId, $ids);
        $this->assertContains($ownCompanyId, $ids);
        $this->assertNotContains($customerId, $ids);
    }

    public function test_contractor_search_matches_full_name(): void
    {
        $admin = $this->createAdminUser();

        $id = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Кратко',
            'full_name' => 'ООО Полное наименование для поиска',
            'inn' => null,
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.search', [
            'type' => 'carrier',
            'q' => 'Полное наименование',
        ]));

        $response->assertOk();
        $ids = collect($response->json('contractors'))->pluck('id')->map(fn ($i): int => (int) $i)->all();
        $this->assertContains($id, $ids);
    }

    public function test_contractor_search_without_type_does_not_default_to_customers(): void
    {
        $admin = $this->createAdminUser();

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'Владелец Автопарка',
            'full_name' => null,
            'inn' => null,
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('contractors.search', [
            'q' => 'Автопарка',
        ]));

        $response->assertOk();
        $ids = collect($response->json('contractors'))->pluck('id')->map(fn ($i): int => (int) $i)->all();
        $this->assertContains($carrierId, $ids);
    }

    public function test_contractor_search_is_allowed_for_user_with_orders_but_without_contractors_area(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'orders_only_test',
            'display_name' => 'Только заказы',
            'visibility_areas' => json_encode(['orders'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        DB::table('contractors')->insert([
            'type' => 'carrier',
            'name' => 'ТК Для Поиска',
            'full_name' => null,
            'inn' => null,
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('contractors.search', [
            'type' => 'carrier',
            'q' => 'Поиска',
        ]));

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('contractors') ?? []));
    }

    public function test_update_contractor_rejects_inactive_user_as_new_owner(): void
    {
        $admin = $this->createAdminUser();
        $activeOwner = User::factory()->create([
            'role_id' => $admin->role_id,
            'is_active' => true,
        ]);
        $inactiveUser = User::factory()->create([
            'role_id' => $admin->role_id,
            'is_active' => false,
        ]);

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Старый владелец',
            'owner_id' => $activeOwner->id,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('contractors.update', $contractorId), [
            'type' => 'customer',
            'name' => 'ООО Старый владелец',
            'stop_on_limit' => false,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'owner_id' => $inactiveUser->id,
        ]);

        $response->assertSessionHasErrors('owner_id');
    }

    public function test_update_contractor_allows_keeping_existing_inactive_owner(): void
    {
        $admin = $this->createAdminUser();
        $inactiveOwner = User::factory()->create([
            'role_id' => $admin->role_id,
            'is_active' => false,
        ]);

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Неактивный владелец',
            'owner_id' => $inactiveOwner->id,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('contractors.update', $contractorId), [
            'type' => 'customer',
            'name' => 'ООО Переименовано',
            'stop_on_limit' => false,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'owner_id' => $inactiveOwner->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'name' => 'ООО Переименовано',
            'owner_id' => $inactiveOwner->id,
        ]);
    }

    public function test_admin_can_mass_update_contractor_owner(): void
    {
        $admin = $this->createAdminUser();
        $oldOwner = User::factory()->create([
            'role_id' => $admin->role_id,
            'is_active' => true,
        ]);
        $newOwner = User::factory()->create([
            'role_id' => $admin->role_id,
            'name' => 'Новый владелец',
            'is_active' => true,
        ]);

        $firstContractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Первый массовый',
            'owner_id' => $oldOwner->id,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondContractorId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Второй массовый',
            'owner_id' => $oldOwner->id,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson(route('contractors.mass-update-owner'), [
            'contractor_ids' => [$firstContractorId, $secondContractorId],
            'owner_id' => $newOwner->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('updated_count', 2)
            ->assertJsonPath('owner_id', $newOwner->id)
            ->assertJsonPath('owner_name', 'Новый владелец');

        foreach ([$firstContractorId, $secondContractorId] as $contractorId) {
            $this->assertDatabaseHas('contractors', [
                'id' => $contractorId,
                'owner_id' => $newOwner->id,
                'updated_by' => $admin->id,
            ]);
        }
    }

    public function test_mass_update_contractor_owner_rejects_inactive_user(): void
    {
        $admin = $this->createAdminUser();
        $inactiveOwner = User::factory()->create([
            'role_id' => $admin->role_id,
            'is_active' => false,
        ]);

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Массовый неактивный',
            'owner_id' => $admin->id,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson(route('contractors.mass-update-owner'), [
            'contractor_ids' => [$contractorId],
            'owner_id' => $inactiveOwner->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('owner_id');
    }

    public function test_store_contractor_rejects_duplicate_company_name(): void
    {
        $admin = $this->createAdminUser();

        DB::table('contractors')->insert([
            'type' => 'customer',
            'name' => 'ООО Дубликат имени',
            'inn' => '1111111111',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('contractors.store'), $this->minimalContractorCreatePayload([
            'name' => 'ООО Дубликат имени',
            'inn' => '2222222222',
        ]));

        $response->assertSessionHasErrors('name');
    }

    public function test_store_contractor_rejects_duplicate_inn_after_normalization(): void
    {
        $admin = $this->createAdminUser();

        DB::table('contractors')->insert([
            'type' => 'customer',
            'name' => 'ООО Первая запись',
            'inn' => '7707083893',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('contractors.store'), $this->minimalContractorCreatePayload([
            'name' => 'ООО Вторая запись',
            'inn' => '77 07 083893',
        ]));

        $response->assertSessionHasErrors('inn');
    }

    public function test_update_contractor_rejects_name_taken_by_another_record(): void
    {
        $admin = $this->createAdminUser();

        $otherId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Чужое имя',
            'inn' => '3333333333',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subjectId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Редактируемый',
            'inn' => '4444444444',
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
            'stop_on_limit' => false,
            'debt_limit_currency' => 'RUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('contractors.update', $subjectId), [
            'type' => 'customer',
            'name' => 'ООО Чужое имя',
            'inn' => '4444444444',
            'stop_on_limit' => false,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseHas('contractors', [
            'id' => $subjectId,
            'name' => 'ООО Редактируемый',
        ]);
    }

    public function test_scoring_route_returns_json_payload_for_contractor(): void
    {
        Config::set('checko.api_key', '');

        $admin = $this->createAdminUser();

        $contractorId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Скоринг',
            'inn' => '1234567890',
            'is_active' => true,
            'is_own_company' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('contractors.scoring', $contractorId));

        $response->assertOk()
            ->assertJson([
                'ok' => false,
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalContractorCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'customer',
            'name' => 'ООО Уникальное название',
            'inn' => '5408231001',
            'stop_on_limit' => false,
            'is_active' => true,
            'is_verified' => false,
            'is_own_company' => false,
        ], $overrides);
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
