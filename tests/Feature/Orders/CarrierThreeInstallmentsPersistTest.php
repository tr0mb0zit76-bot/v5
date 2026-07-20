<?php

namespace Tests\Feature\Orders;

use App\Http\Requests\StoreOrderRequest;
use App\Models\FinancialTerm;
use App\Models\Role;
use App\Models\User;
use App\Support\PaymentInstallmentScheduleNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarrierThreeInstallmentsPersistTest extends TestCase
{
    #[Test]
    public function store_order_request_allows_up_to_ten_installments(): void
    {
        $rules = (new StoreOrderRequest)->rules();

        $this->assertSame(
            ['nullable', 'array', 'max:'.PaymentInstallmentScheduleNormalizer::MAX_INSTALLMENTS],
            $rules['financial_term.contractors_costs.*.payment_schedule.installments'],
        );
        $this->assertSame(
            ['nullable', 'array', 'max:'.PaymentInstallmentScheduleNormalizer::MAX_INSTALLMENTS],
            $rules['financial_term.client_payment_schedule.installments'],
        );

        $installments = [
            $this->installment(50, 275000),
            $this->installment(22.73, 125000),
            $this->installment(27.27, 150000),
        ];

        $validator = Validator::make([
            'financial_term' => [
                'contractors_costs' => [[
                    'payment_schedule' => ['installments' => $installments],
                ]],
            ],
        ], [
            'financial_term.contractors_costs.*.payment_schedule.installments' => $rules['financial_term.contractors_costs.*.payment_schedule.installments'],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.percent' => $rules['financial_term.contractors_costs.*.payment_schedule.installments.*.percent'],
            'financial_term.contractors_costs.*.payment_schedule.installments.*.amount' => $rules['financial_term.contractors_costs.*.payment_schedule.installments.*.amount'],
        ]);

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    #[Test]
    public function three_carrier_installments_persist_on_order_update(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('financial_terms') || ! Schema::hasTable('contractors')) {
            $this->markTestSkipped('Таблицы заказов недоступны.');
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['orders'],
                'visibility_scopes' => ['orders' => 'all'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);

        $clientId = DB::table('contractors')->insertGetId([
            'type' => 'customer',
            'name' => 'ООО Заказчик три транша',
            'inn' => '7700000098',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'type' => 'carrier',
            'name' => 'ООО Три транша',
            'inn' => '7700000097',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderAttrs = [
            'order_number' => 'ORD-TRI-97',
            'company_code' => 'TST',
            'manager_id' => $user->id,
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'customer_id' => $clientId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('orders', 'customer_rate')) {
            $orderAttrs['customer_rate'] = 550000;
        }

        $orderId = DB::table('orders')->insertGetId($orderAttrs);

        if (Schema::hasTable('order_legs')) {
            DB::table('order_legs')->insert([
                'order_id' => $orderId,
                'sequence' => 1,
                'type' => 'transport',
                'description' => 'leg_1',
                'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $installments = [
            $this->installment(50, 275000),
            $this->installment(22.73, 125000),
            $this->installment(27.27, 150000),
        ];

        $clientPaymentForm = 'no_vat';
        if (Schema::hasTable('vat_rates') && DB::table('vat_rates')->where('code', 'vat_22')->exists()) {
            $clientPaymentForm = 'vat_22';
        } elseif (Schema::hasTable('vat_rates') && DB::table('vat_rates')->exists()) {
            $clientPaymentForm = (string) DB::table('vat_rates')->orderBy('sort_order')->value('code');
        }

        $response = $this->actingAs($user)->from(route('orders.edit', $orderId))->patch(route('orders.update', $orderId), [
            'status' => 'new',
            'client_id' => $clientId,
            'order_date' => now()->toDateString(),
            'order_number' => 'ORD-TRI-97',
            'performers' => [[
                'stage' => 'leg_1',
                'contractor_id' => $carrierId,
            ]],
            'route_points' => [
                [
                    'type' => 'loading',
                    'sequence' => 1,
                    'address' => 'Москва',
                    'normalized_data' => [],
                    'planned_date' => now()->toDateString(),
                ],
                [
                    'type' => 'unloading',
                    'sequence' => 2,
                    'address' => 'СПб',
                    'normalized_data' => [],
                    'planned_date' => now()->addDay()->toDateString(),
                ],
            ],
            'cargo_items' => [],
            'financial_term' => [
                'client_price' => 550000,
                'client_currency' => 'RUB',
                'client_payment_form' => $clientPaymentForm,
                'client_payment_schedule' => [
                    'installments' => [$this->installment(100, 550000)],
                ],
                'contractors_costs' => [[
                    'stage' => 'leg_1',
                    'contractor_id' => $carrierId,
                    'amount' => 550000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [
                        'installments' => $installments,
                    ],
                    'payment_terms' => '',
                ]],
                'additional_costs' => [],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $ft = FinancialTerm::query()->where('order_id', $orderId)->first();
        $this->assertNotNull($ft);

        $costs = is_array($ft->contractors_costs) ? $ft->contractors_costs : [];
        $this->assertNotEmpty($costs);

        $saved = $costs[0]['payment_schedule']['installments'] ?? [];
        $this->assertCount(3, $saved);
        $this->assertEqualsWithDelta(50.0, (float) ($saved[0]['percent'] ?? 0), 0.05);
        $this->assertEqualsWithDelta(22.73, (float) ($saved[1]['percent'] ?? 0), 0.05);
        $this->assertEqualsWithDelta(27.27, (float) ($saved[2]['percent'] ?? 0), 0.05);
        $savedSum = array_sum(array_map(static fn (array $row): float => (float) ($row['amount'] ?? 0), $saved));
        $this->assertEqualsWithDelta(550000.0, $savedSum, 1.0);

        if (Schema::hasTable('payment_schedules') && Schema::hasColumn('payment_schedules', 'installment_sequence')) {
            $rows = DB::table('payment_schedules')
                ->where('order_id', $orderId)
                ->where('party', 'carrier')
                ->orderBy('installment_sequence')
                ->get();

            $this->assertCount(3, $rows);
            $ledgerSum = (float) $rows->sum('amount');
            $this->assertEqualsWithDelta(550000.0, $ledgerSum, 1.0);
        }
    }

    /**
     * @return array{percent: float, amount: float, offset_days: int, offset_unit: string, anchor: string, basis: string}
     */
    private function installment(float $percent, float $amount): array
    {
        return [
            'percent' => $percent,
            'amount' => $amount,
            'offset_days' => 0,
            'offset_unit' => 'calendar_days',
            'anchor' => 'last_unloading',
            'basis' => 'ottn',
        ];
    }
}
