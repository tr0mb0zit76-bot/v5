<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementAccountingTotalsSplitter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingTotalsSplitterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['management_expense_categories']);

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('flow', 8)->default('out');
            $table->boolean('include_in_budget')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_splits_cost_budget_and_other_outflows(): void
    {
        $expenseRoot = ManagementExpenseCategory::query()->create([
            'code' => 'group_expense',
            'name' => 'Расходы',
            'kind' => 'group',
            'flow' => 'out',
        ]);

        $costGroup = ManagementExpenseCategory::query()->create([
            'code' => 'group_cost',
            'name' => 'Себестоимость',
            'kind' => 'group',
            'flow' => 'out',
            'parent_id' => $expenseRoot->id,
        ]);

        $carrier = ManagementExpenseCategory::query()->create([
            'code' => 'operational_carrier_out',
            'name' => 'Привлечённый транспорт',
            'kind' => 'operational_out_hired',
            'flow' => 'out',
            'parent_id' => $costGroup->id,
        ]);

        $bankFees = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банковские комиссии',
            'kind' => 'overhead',
            'flow' => 'out',
            'include_in_budget' => true,
            'parent_id' => $expenseRoot->id,
        ]);

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
