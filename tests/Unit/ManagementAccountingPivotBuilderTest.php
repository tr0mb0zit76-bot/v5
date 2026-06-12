<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Services\ManagementAccounting\ManagementAccountingPivotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingPivotBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_statement_lines',
            'management_expense_categories',
            'budget_opex_articles',
        ]);

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('flow', 8)->default('out');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_pivot_shows_percent_only_for_expense_rows(): void
    {
        $income = ManagementExpenseCategory::query()->create([
            'code' => 'operational_customer_in',
            'name' => 'Доходы',
            'kind' => 'operational_in',
            'flow' => 'in',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $expense = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банк',
            'kind' => 'overhead',
            'flow' => 'out',
            'is_active' => true,
            'sort_order' => 20,
        ]);

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
