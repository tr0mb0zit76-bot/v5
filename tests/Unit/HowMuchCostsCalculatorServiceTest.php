<?php

namespace Tests\Unit;

use App\Services\HowMuchCostsCalculatorService;
use App\Services\OwnFleetCostNormsService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HowMuchCostsCalculatorServiceTest extends TestCase
{
    public function test_customer_price_applies_percent_then_absolute_margin(): void
    {
        if (! Schema::hasTable('own_fleet_cost_norms')) {
            $this->markTestSkipped('Таблица own_fleet_cost_norms недоступна.');
        }

        app(OwnFleetCostNormsService::class)->update([
            'cn' => [
                'fuel_price_rub_per_liter' => 60,
                'fuel_consumption_l_per_100km' => 10,
                'driver_rub_per_km' => 4,
                'other_rub_per_km' => 0,
            ],
            'ru' => [
                'fuel_price_rub_per_liter' => 60,
                'fuel_consumption_l_per_100km' => 10,
                'driver_rub_per_km' => 4,
                'other_rub_per_km' => 0,
            ],
            'depreciation_rub_per_km' => 0,
            'margin_percent' => 15,
            'margin_absolute_rub' => 5000,
        ]);

        $result = app(HowMuchCostsCalculatorService::class)->calculate([
            'km_to_border' => 100,
            'km_from_border' => 100,
        ]);

        // fuel 6 ₽/км + driver 4 = 10 ₽/км × 200 км = 2000
        $this->assertSame(2000.0, $result['totals']['cost_price']);
        $this->assertSame(300.0, $result['totals']['margin_from_percent_rub']);
        $this->assertSame(5000.0, $result['totals']['margin_absolute_rub']);
        $this->assertSame(7300.0, $result['totals']['customer_price']);
    }

    public function test_margin_overrides_replace_norm_defaults(): void
    {
        if (! Schema::hasTable('own_fleet_cost_norms')) {
            $this->markTestSkipped('Таблица own_fleet_cost_norms недоступна.');
        }

        app(OwnFleetCostNormsService::class)->update([
            'cn' => [
                'fuel_price_rub_per_liter' => 0,
                'fuel_consumption_l_per_100km' => 0,
                'driver_rub_per_km' => 10,
                'other_rub_per_km' => 0,
            ],
            'ru' => [
                'fuel_price_rub_per_liter' => 0,
                'fuel_consumption_l_per_100km' => 0,
                'driver_rub_per_km' => 0,
                'other_rub_per_km' => 0,
            ],
            'depreciation_rub_per_km' => 0,
            'margin_percent' => 10,
            'margin_absolute_rub' => 1000,
        ]);

        $result = app(HowMuchCostsCalculatorService::class)->calculate([
            'km_to_border' => 100,
            'km_from_border' => 0,
            'margin_percent' => 20,
            'margin_absolute_rub' => 0,
        ]);

        $this->assertSame(1000.0, $result['totals']['cost_price']);
        $this->assertSame(200.0, $result['totals']['margin_from_percent_rub']);
        $this->assertSame(0.0, $result['totals']['margin_absolute_rub']);
        $this->assertSame(1200.0, $result['totals']['customer_price']);
    }
}
