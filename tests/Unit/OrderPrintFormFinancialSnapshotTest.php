<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderPrintFormDraftService;
use App\Services\PrintFormVariableCatalog;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class OrderPrintFormFinancialSnapshotTest extends TestCase
{
    #[Test]
    public function build_snapshot_includes_financial_norms_from_wizard_state(): void
    {
        if (! Schema::hasColumn((new Order)->getTable(), 'wizard_state')) {
            $this->markTestSkipped('orders.wizard_state column not present');
        }

        $order = Order::factory()->create();
        $order->forceFill([
            'wizard_state' => [
                'financial_term' => [
                    'client_norms_penalties' => [
                        'miss_amount' => 1000,
                        'miss_currency' => 'USD',
                        'downtime_amount' => 500,
                        'downtime_currency' => 'RUB',
                        'fine_amount' => 100,
                        'fine_currency' => 'EUR',
                        'penalty_terms' => ' 0,1% в день ',
                        'norm_loading_hours' => 24,
                        'norm_customs_hours' => 48.5,
                        'norm_unloading_hours' => 12,
                    ],
                    'carrier_norms_by_leg' => [
                        [
                            'stage' => 'leg-a',
                            'miss_amount' => 200,
                            'miss_currency' => 'RUB',
                            'penalty_terms' => '',
                            'norm_unloading_hours' => 6,
                        ],
                    ],
                ],
            ],
        ])->saveQuietly();

        $order->refresh();

        $service = app(OrderPrintFormDraftService::class);
        $order = $service->loadOrderContext($order);

        $method = new ReflectionMethod(OrderPrintFormDraftService::class, 'buildSnapshot');
        $method->setAccessible(true);
        /** @var array<string, mixed> $snapshot */
        $snapshot = $method->invoke($service, $order);

        $this->assertSame('1 000,00 USD', data_get($snapshot, 'financial.client_norms_penalties.miss_amount_with_currency'));
        $this->assertSame('500,00 RUB', data_get($snapshot, 'financial.client_norms_penalties.downtime_amount_with_currency'));
        $this->assertSame('100,00 EUR', data_get($snapshot, 'financial.client_norms_penalties.fine_amount_with_currency'));
        $this->assertSame('0,1% в день', data_get($snapshot, 'financial.client_norms_penalties.penalty_terms'));
        $this->assertSame('24', data_get($snapshot, 'financial.client_norms_penalties.norm_loading_hours'));
        $this->assertSame('48,5', data_get($snapshot, 'financial.client_norms_penalties.norm_customs_hours'));
        $this->assertSame('12', data_get($snapshot, 'financial.client_norms_penalties.norm_unloading_hours'));

        $this->assertSame('leg-a', data_get($snapshot, 'financial.carrier_norms_by_leg.0.stage'));
        $this->assertSame('200,00 RUB', data_get($snapshot, 'financial.carrier_norms_by_leg.0.miss_amount_with_currency'));
        $this->assertNull(data_get($snapshot, 'financial.carrier_norms_by_leg.0.penalty_terms'));
        $this->assertSame('6', data_get($snapshot, 'financial.carrier_norms_by_leg.0.norm_unloading_hours'));
    }

    #[Test]
    public function print_form_variable_catalog_contains_financial_paths(): void
    {
        $catalog = app(PrintFormVariableCatalog::class);
        $values = array_column($catalog->orderOptions(), 'value');

        $this->assertContains('financial.client_norms_penalties.miss_amount_with_currency', $values);
        $this->assertContains('financial.carrier_norms_penalties.norm_unloading_hours', $values);
        $this->assertContains('financial.carrier_norms_penalties.penalty_terms', $values);
    }
}
