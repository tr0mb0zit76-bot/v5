<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementAccountingTotalsSplitter;
use Tests\TestCase;

class ManagementAccountingTotalsSplitterTest extends TestCase
{
    public function test_splits_cost_budget_and_other_outflows(): void
    {
        $carrier = ManagementExpenseCategory::query()
            ->where('code', 'operational_carrier_out')
            ->firstOrFail();

        $bankFees = ManagementExpenseCategory::query()
            ->where('code', 'bank_fees')
            ->firstOrFail();

        $categories = ManagementExpenseCategory::query()->get();
        $byCategory = [
            $carrier->id => ['in' => 0.0, 'out' => 78000.0],
            $bankFees->id => ['in' => 0.0, 'out' => 1500.0],
        ];

        $result = app(ManagementAccountingTotalsSplitter::class)->split(
            $categories,
            $byCategory,
            500000.0,
            79500.0,
            100000.0,
        );

        $this->assertSame(78000.0, $result['actual_out_cost']);
        $this->assertSame(1500.0, $result['actual_out_budget']);
        $this->assertSame(0.0, $result['actual_out_other']);
        $this->assertSame(422000.0, $result['gross_margin']);
        $this->assertSame(84.4, $result['gross_margin_percent']);
        $this->assertSame(-98500.0, $result['budget_variance']);
        $this->assertSame(1.5, $result['budget_execution_percent']);
    }
}
