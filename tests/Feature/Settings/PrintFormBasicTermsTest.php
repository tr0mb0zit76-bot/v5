<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Models\User;
use App\Services\PrintForm\PrintFormBasicTermsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrintFormBasicTermsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'print_form_basic_terms',
            'orders',
            'contractors',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
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
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->json('customer_basic_terms')->nullable();
            $table->json('carrier_basic_terms')->nullable();
            $table->timestamps();
        });

        Schema::create('print_form_basic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('party', 16);
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function test_admin_can_open_basic_terms_tab(): void
    {
        $admin = $this->adminUser();

        DB::table('print_form_basic_terms')->insert([
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => null,
            'sort_order' => 1,
            'body' => 'Общий пункт 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('settings.templates.index', [
            'tab' => 'basic-terms',
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Templates')
            ->where('pageTab', 'basic-terms')
            ->has('basicTermsEditor.rows', 1)
            ->where('basicTermsEditor.rows.0.body', 'Общий пункт 1')
            ->where('basicTermsEditor.placeholderHelp.customer.anchor', 'cp_basic_terms_row_text'));
    }

    public function test_admin_can_save_global_customer_basic_terms(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->put(route('settings.templates.basic-terms.update'), [
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => null,
            'items' => [
                'Пункт А',
                'Пункт Б',
            ],
        ]);

        $response->assertRedirect(route('settings.templates.index', [
            'tab' => 'basic-terms',
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
        ]));

        $this->assertDatabaseHas('print_form_basic_terms', [
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => null,
            'sort_order' => 1,
            'body' => 'Пункт А',
        ]);

        $this->assertDatabaseHas('print_form_basic_terms', [
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => null,
            'sort_order' => 2,
            'body' => 'Пункт Б',
        ]);
    }

    public function test_service_resolves_order_override_before_contractor_and_global(): void
    {
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Заказчик X',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('print_form_basic_terms')->insert([
            [
                'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
                'contractor_id' => null,
                'sort_order' => 1,
                'body' => 'Глобальный пункт',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
                'contractor_id' => $customerId,
                'sort_order' => 1,
                'body' => 'Пункт для контрагента',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'customer_id' => $customerId,
            'carrier_id' => null,
            'customer_basic_terms' => json_encode(['Переопределение в заказе'], JSON_THROW_ON_ERROR),
            'carrier_basic_terms' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::query()->findOrFail($orderId);
        $service = app(PrintFormBasicTermsService::class);

        $this->assertSame(
            ['Переопределение в заказе'],
            $service->resolveTermBodies($order, PrintFormBasicTerm::PARTY_CUSTOMER),
        );

        DB::table('orders')->where('id', $orderId)->update(['customer_basic_terms' => null]);

        $order->refresh();

        $this->assertSame(
            ['Пункт для контрагента'],
            $service->resolveTermBodies($order->fresh(), PrintFormBasicTerm::PARTY_CUSTOMER),
        );
    }

    public function test_admin_can_promote_order_basic_terms_to_contractor(): void
    {
        $admin = $this->adminUser();

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Заказчик Y',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('print_form_basic_terms')->insert([
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => null,
            'sort_order' => 1,
            'body' => 'Глобальный пункт',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'customer_id' => $customerId,
            'carrier_id' => null,
            'customer_basic_terms' => json_encode(['Новая база для контрагента'], JSON_THROW_ON_ERROR),
            'carrier_basic_terms' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('orders.basic-terms.promote', $orderId), [
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('print_form_basic_terms', [
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => $customerId,
            'sort_order' => 1,
            'body' => 'Новая база для контрагента',
        ]);
    }

    public function test_normalize_order_override_clears_when_equal_to_baseline(): void
    {
        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'Заказчик Z',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('print_form_basic_terms')->insert([
            'party' => PrintFormBasicTerm::PARTY_CUSTOMER,
            'contractor_id' => $customerId,
            'sort_order' => 1,
            'body' => 'Базовый пункт',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'customer_id' => $customerId,
            'carrier_id' => null,
            'customer_basic_terms' => null,
            'carrier_basic_terms' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::query()->findOrFail($orderId);
        $service = app(PrintFormBasicTermsService::class);

        $this->assertNull(
            $service->normalizeOrderOverride(['Базовый пункт'], $order, PrintFormBasicTerm::PARTY_CUSTOMER),
        );

        $this->assertSame(
            ['Изменённый пункт'],
            $service->normalizeOrderOverride(['Изменённый пункт'], $order, PrintFormBasicTerm::PARTY_CUSTOMER),
        );
    }

    private function adminUser(): User
    {
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Администратор',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create(['role_id' => $roleId]);
    }
}
