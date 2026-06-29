<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementAccountingPivotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ManagementAccountingPivotBuilderTest extends TestCase
{
    public function test_pivot_shows_percent_only_for_expense_rows(): void
    {
        $income = ManagementExpenseCategory::query()
            ->where('code', 'operational_customer_in')
            ->firstOrFail();

        $expense = ManagementExpenseCategory::query()
            ->where('code', 'bank_fees')
            ->firstOrFail();

        $categories = new Collection([$income, $expense]);
        $start = CarbonImmutable::parse('2026-06-01');
        $end = CarbonImmutable::parse('2026-06-30');

        $leafActuals = [
            $income->id => [
                '2026-06' => ['in' => 100000.0, 'out' => 0.0],
            ],
            $expense->id => [
                '2026-06' => ['in' => 0.0, 'out' => 20000.0],
            ],
        ];

        $builder = app(ManagementAccountingPivotBuilder::class);
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('rollupCells');
        $method->setAccessible(true);

        $incomeCells = $method->invoke(
            $builder,
            $income,
            [$income->id],
            [['key' => '2026-06']],
            $leafActuals,
            ['2026-06' => 100000.0],
        );

        $expenseCells = $method->invoke(
            $builder,
            $expense,
            [$expense->id],
            [['key' => '2026-06']],
            $leafActuals,
            ['2026-06' => 100000.0],
        );

        $this->assertNull($incomeCells[0]['percent']);
        $this->assertSame(20.0, $expenseCells[0]['percent']);
    }
}
