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
            $table->string('bank_name')->nullable();
            $table->string('bik', 9)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('correspondent_account', 20)->nullable();
            $table->json('ati_profiles')->nullable();
            $table->string('ati_id')->nullable();
            $table->json('transport_requirements')->nullable();
            $table->json('specializations')->nullable();
            $table->decimal('rating', 5, 2)->nullable();
            $table->unsignedInteger('completed_orders')->default(0);
            $table->json('metadata')->nullable();
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
            $table->string('status')->nullable();
            $table->date('order_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
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
            'transport_requirements' => ['Страховка', 'GPS'],
            'is_active' => true,
            'is_verified' => true,
            'is_own_company' => true,
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
        ]);
        $this->assertDatabaseHas('contractor_contacts', [
            'contractor_id' => $contractorId,
            'full_name' => 'Иван Петров',
        ]);
        $this->assertDatabaseHas('contractor_documents', [
            'contractor_id' => $contractorId,
            'title' => 'Договор поставки',
        ]);
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
            'bank_name' => '',
            'bik' => '',
            'account_number' => '',
            'correspondent_account' => '',
            'ati_id' => '',
            'specializations' => ['Тент'],
            'transport_requirements' => [],
            'is_active' => false,
            'is_verified' => false,
            'is_own_company' => true,
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
            'updated_by' => $admin->id,
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
