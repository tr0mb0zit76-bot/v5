<?php

namespace Tests\Unit;

use App\Services\OwnFleetCostNormsService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OwnFleetCostNormsServiceTest extends TestCase
{
    public function test_fuel_cost_per_km_from_price_and_consumption(): void
    {
        $service = app(OwnFleetCostNormsService::class);

        $this->assertSame(6.0, $service->fuelCostRubPerKm(60.0, 10.0));
        $this->assertSame(0.0, $service->fuelCostRubPerKm(60.0, 0.0));
    }

    public function test_update_persists_norms_and_derives_fuel_cost(): void
    {
        if (! Schema::hasTable('own_fleet_cost_norms')) {
            $this->markTestSkipped('Таблица own_fleet_cost_norms недоступна.');
        }

        $service = app(OwnFleetCostNormsService::class);

        $payload = $service->update([
            'cn' => [
                'fuel_price_rub_per_liter' => 60,
                'fuel_consumption_l_per_100km' => 10,
                'driver_rub_per_km' => 5,
                'other_rub_per_km' => 1,
            ],
            'ru' => [
                'fuel_price_rub_per_liter' => 55,
                'fuel_consumption_l_per_100km' => 10,
                'driver_rub_per_km' => 8,
                'other_rub_per_km' => 2,
            ],
            'depreciation_rub_per_km' => 3,
            'margin_percent' => 15,
            'margin_absolute_rub' => 5000,
        ]);

        $this->assertSame(6.0, $payload['cn']['fuel_cost_rub_per_km']);
        $this->assertSame(5.5, $payload['ru']['fuel_cost_rub_per_km']);
        $this->assertSame(15.0, $payload['margin_percent']);
        $this->assertSame(5000.0, $payload['margin_absolute_rub']);
    }
}
