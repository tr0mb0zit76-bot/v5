<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Models\User;
use App\Services\PrintForm\PrintFormBasicTermsService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrintFormBasicTermsTest extends TestCase
{
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

        $orderId = $this->insertOrderRow([
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

        $orderId = $this->insertOrderRow([
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

        $orderId = $this->insertOrderRow([
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
