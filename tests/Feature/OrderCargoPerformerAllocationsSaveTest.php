<?php

namespace Tests\Feature;

use App\Http\Requests\UpdateOrderRequest;
use App\Models\Cargo;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class OrderCargoPerformerAllocationsSaveTest extends TestCase
{
    public function test_validated_request_keeps_cargo_performer_allocations(): void
    {
        $rules = (new UpdateOrderRequest)->rules();

        $payload = [
            'cargo_items' => [
                [
                    'name' => 'Тест',
                    'cargo_type' => 'general',
                    'package_count' => 5,
                    'performer_allocations' => [
                        ['stage' => 'leg_1', 'carrier_slot' => null, 'package_count' => 5],
                    ],
                ],
            ],
        ];

        $validator = validator($payload, [
            'cargo_items' => $rules['cargo_items'],
            'cargo_items.*.name' => $rules['cargo_items.*.name'],
            'cargo_items.*.cargo_type' => $rules['cargo_items.*.cargo_type'],
            'cargo_items.*.package_count' => $rules['cargo_items.*.package_count'],
            'cargo_items.*.performer_allocations' => $rules['cargo_items.*.performer_allocations'],
            'cargo_items.*.performer_allocations.*.stage' => $rules['cargo_items.*.performer_allocations.*.stage'],
            'cargo_items.*.performer_allocations.*.carrier_slot' => $rules['cargo_items.*.performer_allocations.*.carrier_slot'],
            'cargo_items.*.performer_allocations.*.package_count' => $rules['cargo_items.*.performer_allocations.*.package_count'],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));

        $validated = $validator->validated();
        $this->assertSame(5.0, (float) $validated['cargo_items'][0]['performer_allocations'][0]['package_count']);
    }

    public function test_order_wizard_update_persists_performer_allocations_in_cargo_payload(): void
    {
        $order = Order::query()->find(1);
        $user = User::query()->first();

        if ($order === null || $user === null) {
            $this->markTestSkipped('Order #1 or user required for integration check.');
        }

        Auth::login($user);

        $validated = [
            'status' => $order->status,
            'client_id' => $order->customer_id,
            'order_date' => $order->order_date?->format('Y-m-d') ?? '2026-05-28',
            'order_number' => $order->order_number,
            'performers' => [
                ['stage' => 'leg_1', 'carrier_mode' => 'single', 'contractor_id' => 2, 'split_carriers' => []],
                ['stage' => 'leg_2', 'carrier_mode' => 'split', 'contractor_id' => null, 'split_carriers' => [
                    ['slot' => 1, 'contractor_id' => 12],
                    ['slot' => 2, 'contractor_id' => 16],
                ]],
            ],
            'route_points' => [
                ['type' => 'loading', 'sequence' => 1, 'stage' => 'leg_1', 'address' => 'Адрес погрузки', 'normalized_data' => []],
                ['type' => 'unloading', 'sequence' => 2, 'stage' => 'leg_2', 'address' => 'Адрес выгрузки', 'normalized_data' => []],
            ],
            'cargo_items' => [[
                'name' => 'Конвейерная лента',
                'cargo_type' => 'general',
                'package_count' => 5,
                'weight_value' => 2500,
                'weight_unit' => 'kg',
                'performer_allocations' => [
                    ['stage' => 'leg_1', 'carrier_slot' => null, 'package_count' => 5, 'weight_value' => 12500],
                    ['stage' => 'leg_2', 'carrier_slot' => 1, 'package_count' => 3, 'weight_value' => 7500],
                    ['stage' => 'leg_2', 'carrier_slot' => 2, 'package_count' => 2, 'weight_value' => 5000],
                ],
            ]],
            'financial_term' => [
                'client_price' => (float) ($order->customer_rate ?? 1000),
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat_0',
                'contractors_costs' => [
                    ['stage' => 'leg_1', 'contractor_id' => 2, 'amount' => 840000, 'currency' => 'RUB', 'payment_form' => 'vat_0'],
                    ['stage' => 'leg_2', 'carrier_slot' => 1, 'contractor_id' => 12, 'amount' => 600000, 'currency' => 'RUB', 'payment_form' => 'vat_22'],
                    ['stage' => 'leg_2', 'carrier_slot' => 2, 'contractor_id' => 16, 'amount' => 480000, 'currency' => 'RUB', 'payment_form' => 'vat_22'],
                ],
            ],
        ];

        app(OrderWizardService::class)->update($order->fresh(), $validated, $user);

        $cargo = Cargo::query()->where('order_id', 1)->latest('id')->first();
        $this->assertNotNull($cargo);
        $allocations = $cargo->ati_cargo_payload['performer_allocations'] ?? null;
        $this->assertIsArray($allocations);
        $this->assertCount(3, $allocations);
        $this->assertSame(5.0, (float) $allocations[0]['package_count']);
    }
}
