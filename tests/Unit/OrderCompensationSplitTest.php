<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCompensationService;
use App\Support\OrderCompensationSplitResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCompensationSplitTest extends TestCase
{
    #[Test]
    public function apply_to_salary_splits_total_by_owner_and_dispatcher_percent(): void
    {
        $split = [
            'active' => true,
            'owner_percent' => 70.0,
            'dispatcher_percent' => 30.0,
            'dispatcher_id' => 5,
        ];

        $result = OrderCompensationSplitResolver::applyToSalary(10000.0, $split);

        $this->assertSame(10000.0, $result['total']);
        $this->assertSame(7000.0, $result['owner']);
        $this->assertSame(3000.0, $result['dispatcher']);
    }

    #[Test]
    public function refresh_order_compensation_persists_owner_salary_and_split_in_metadata(): void
    {
        if (! Schema::hasColumn('orders', 'dispatcher_id') || ! Schema::hasColumn('orders', 'metadata')) {
            $this->markTestSkipped('dispatcher_id or metadata column missing');
        }

        $owner = User::factory()->create();
        $dispatcher = User::factory()->create();

        DB::table('salary_coefficients')->insert([
            'manager_id' => $owner->id,
            'base_salary' => 0,
            'bonus_percent' => 50,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'SPLIT-001',
            'manager_id' => $owner->id,
            'order_owner_id' => $owner->id,
            'dispatcher_id' => $dispatcher->id,
            'order_date' => '2026-07-09',
            'customer_rate' => 100000,
            'customer_payment_form' => 'vat_22',
            'status' => 'new',
            'is_active' => true,
            'metadata' => json_encode([
                'compensation_split' => [
                    'order_owner_id' => $owner->id,
                    'dispatcher_id' => $dispatcher->id,
                    'order_owner_percent' => 70,
                    'dispatcher_percent' => 30,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        if (Schema::hasTable('financial_terms')) {
            DB::table('financial_terms')->insert([
                'order_id' => $orderId,
                'client_price' => 100000,
                'client_currency' => 'RUB',
                'contractors_costs' => json_encode([
                    ['amount' => 70000, 'payment_form' => 'vat_22', 'stage' => 'leg_1'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $order = Order::query()->findOrFail($orderId);

        app(OrderCompensationService::class)->refreshOrderCompensationFields($order->fresh());

        $order->refresh();
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $split = $metadata['compensation_split'] ?? [];

        $this->assertGreaterThan(0, (float) $order->salary_accrued);
        $this->assertArrayHasKey('salary_accrued_total', $split);
        $this->assertArrayHasKey('salary_accrued_owner', $split);
        $this->assertArrayHasKey('salary_accrued_dispatcher', $split);
        $this->assertSame((float) $order->salary_accrued, (float) $split['salary_accrued_owner']);
        $this->assertGreaterThan(0, (float) $split['salary_accrued_dispatcher']);
        $this->assertEqualsWithDelta(
            (float) $split['salary_accrued_total'],
            (float) $split['salary_accrued_owner'] + (float) $split['salary_accrued_dispatcher'],
            0.02,
        );
    }

    #[Test]
    public function calculate_realtime_returns_split_salary_for_dispatcher(): void
    {
        $service = app(OrderCompensationService::class);

        $result = $service->calculateRealtime([
            'customer_rate' => 1000,
            'carrier_rate' => 400,
            'additional_expenses' => 0,
            'insurance' => 0,
            'bonus' => 0,
            'manager_id' => 1,
            'dispatcher_id' => 2,
            'compensation_owner_percent' => 60,
            'compensation_dispatcher_percent' => 40,
            'order_date' => '2026-05-31',
            'customer_payment_form' => 'vat_22',
            'contractors_costs' => [
                ['payment_form' => 'vat_22', 'amount' => 400],
            ],
        ]);

        $this->assertArrayHasKey('compensation_split_salary', $result);
        $this->assertSame($result['salary_accrued'], $result['compensation_split_salary']['owner']);
        $this->assertGreaterThan(0, $result['compensation_split_salary']['dispatcher']);
    }
}
