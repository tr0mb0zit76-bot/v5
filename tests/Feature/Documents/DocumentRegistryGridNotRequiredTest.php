<?php

namespace Tests\Feature\Documents;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentRegistryGridNotRequiredTest extends TestCase
{
    #[Test]
    public function documents_index_marks_carrier_closing_as_not_required_for_cash_carrier(): void
    {
        if (! Schema::hasColumn('orders', 'performers')) {
            $this->markTestSkipped('Column orders.performers is required for carrier document rules.');
        }

        $manager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);

        $orderAttributes = [
            'manager_id' => $manager->id,
            'order_number' => 'DOC-NR-1',
            'customer_payment_form' => 'cash',
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => 15,
                'contractor_name' => 'Перевозчик',
            ]],
        ];

        $order = Order::factory()->create($orderAttributes);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'contractors_costs' => [[
                'contractor_id' => 15,
                'payment_form' => 'cash',
                'amount' => 10000,
            ]],
        ]);

        $this->actingAs($manager)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.order_id', $order->id)
                ->where('rows.0.column_applicable.carrier_upd', false)
                ->where('rows.0.column_applicable.carrier_act', false)
                ->where('rows.0.column_applicable.carrier_invoice_factura', false)
                ->where('rows.0.column_applicable.carrier_request', true)
                ->where('rows.0.column_applicable.customer_upd', false)
                ->where('rows.0.column_applicable.customer_request', true),
            );
    }

    #[Test]
    public function documents_index_marks_all_carrier_columns_not_required_for_own_fleet_cash(): void
    {
        if (! Schema::hasColumn('orders', 'performers')) {
            $this->markTestSkipped('Column orders.performers is required for own fleet document rules.');
        }

        $manager = $this->makeUser(['documents', 'orders'], ['documents' => 'own', 'orders' => 'own']);

        $orderAttributes = [
            'manager_id' => $manager->id,
            'order_number' => 'DOC-NR-OWN',
            'customer_payment_form' => 'cash',
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => 99,
                'contractor_name' => 'Собственный парк',
                'execution_mode' => 'own_fleet',
            ]],
        ];

        $order = Order::factory()->create($orderAttributes);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'contractors_costs' => [[
                'contractor_id' => 99,
                'payment_form' => 'cash',
                'execution_mode' => 'own_fleet',
                'amount' => 5000,
            ]],
        ]);

        $this->actingAs($manager)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.column_applicable.carrier_upd', false)
                ->where('rows.0.column_applicable.carrier_request', false)
                ->where('rows.0.column_applicable.carrier_invoice', false)
                ->where('rows.0.column_applicable.transport_docs', false)
                ->where('rows.0.needs_track_received_date_carrier', false)
                ->where('rows.0.column_applicable.customer_upd', false)
                ->where('rows.0.column_applicable.customer_request', true),
            );
    }

    /**
     * @param  list<string>  $areas
     * @param  array<string, string>  $scopes
     */
    private function makeUser(array $areas, array $scopes = []): User
    {
        $role = Role::query()->create([
            'name' => 'documents_grid_nr_'.uniqid(),
            'display_name' => 'Documents Grid NR',
            'permissions' => [],
            'visibility_areas' => $areas,
            'visibility_scopes' => $scopes,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
