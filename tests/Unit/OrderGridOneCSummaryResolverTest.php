<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Support\OrderGridOneCSummaryResolver;
use Tests\TestCase;

class OrderGridOneCSummaryResolverTest extends TestCase
{
    public function test_grid_summary_uses_leg_metadata_when_order_performers_missing(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ООО Перевозчик',
            'type' => 'carrier',
        ]);

        $vehicle = FleetVehicle::query()->create([
            'owner_contractor_id' => $carrier->id,
            'tractor_brand' => 'Volvo',
            'tractor_plate' => 'К111КК77',
            'trailer_brand' => 'Krone',
            'trailer_plate' => 'Е222ЕЕ77',
        ]);

        $driver = FleetDriver::query()->create([
            'carrier_contractor_id' => $carrier->id,
            'full_name' => 'Петров Пётр Петрович',
        ]);

        $order = Order::factory()->create([
            'company_code' => 'AA',
            'order_number' => 'ORD-GRID-1',
            'order_date' => '2026-06-16',
            'customer_rate' => 50000,
            'customer_payment_form' => 'no_vat',
        ]);

        OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'metadata' => [
                'performer' => [
                    'stage' => 'leg_1',
                    'contractor_id' => $carrier->id,
                    'fleet_vehicle_id' => $vehicle->id,
                    'fleet_driver_id' => $driver->id,
                ],
            ],
        ]);

        $row = [
            'id' => $order->id,
            'company_code' => 'AA',
            'customer_name' => 'ООО Клиент',
            'order_number' => 'ORD-GRID-1',
            'order_date' => '2026-06-16',
            'customer_rate' => 50000,
            'customer_payment_form' => 'no_vat',
            'loading_point' => 'Самара',
            'last_unloading_point' => 'Уфа',
            'performers' => null,
            'driver_id' => null,
        ];

        $enriched = app(OrderGridOneCSummaryResolver::class)->enrich(collect([$row]))->first();

        $summary = (string) ($enriched['clipboard_summary'] ?? '');
        $this->assertStringContainsString('Volvo / К111КК77 / Krone / Е222ЕЕ77', $summary);
        $this->assertStringContainsString('Петров Пётр Петрович', $summary);
    }
}
