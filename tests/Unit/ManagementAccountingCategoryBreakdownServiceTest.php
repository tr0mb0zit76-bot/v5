<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\ManagementAccounting\ManagementAccountingCategoryBreakdownService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ManagementAccountingCategoryBreakdownServiceTest extends TestCase
{
    public function test_cost_breakdown_groups_carrier_payments_by_order(): void
    {
        $costGroup = ManagementExpenseCategory::query()
            ->where('code', 'group_cost')
            ->firstOrFail();

        $carrierCategory = ManagementExpenseCategory::query()
            ->where('code', 'operational_carrier_out')
            ->firstOrFail();

        $orderId = $this->insertOrderRow(['order_number' => '105']);
        $bankAccountId = $this->createManagementBankAccount()->id;

        $this->createManagementStatementLine([
            'bank_account_id' => $bankAccountId,
            'line_hash' => 'line-1',
            'operation_date' => '2026-06-05',
            'direction' => 'out',
            'amount' => 120000,
            'status' => 'allocated',
            'allocation_category_id' => $carrierCategory->id,
            'allocation_order_id' => $orderId,
        ]);

        PaymentSchedulePaymentEvent::query()->create([
            'order_id' => $orderId,
            'party' => 'carrier',
            'amount' => 30000,
            'payment_date' => '2026-06-08',
            'transaction_reference' => null,
        ]);

        $categories = ManagementExpenseCategory::query()->get();
        $start = CarbonImmutable::parse('2026-06-01');
        $end = CarbonImmutable::parse('2026-06-30');

        $result = app(ManagementAccountingCategoryBreakdownService::class)
            ->forCategory($costGroup, $categories, $start, $end);

        $this->assertSame('order', $result['label']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Заказ 105', $result['items'][0]['name']);
        $this->assertSame(150000.0, $result['items'][0]['actual_out']);
    }
}
