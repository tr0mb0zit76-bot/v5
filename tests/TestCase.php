<?php

namespace Tests;

use App\Models\FinancialTerm;
use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Удаление таблиц в тестах без ошибки MySQL 3730 (FK), если в БД есть «чужие» дочерние таблицы.
     *
     * @param  list<string>  $tables
     */
    protected function schemaDropMany(array $tables): void
    {
        if ($tables === []) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * MySQL не откатывает DDL (DROP/ALTER) в транзакции RefreshDatabase.
     */
    protected function restoreTestDatabaseSchema(): void
    {
        $this->refreshTestDatabase();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function onlyExistingOrderColumns(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn (mixed $value, string $key): bool => Schema::hasColumn('orders', $key),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertOrderRow(array $attributes): int
    {
        $attributes = $this->onlyExistingOrderColumns(array_merge([
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return (int) DB::table('orders')->insertGetId($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function assertDatabaseHasOrder(array $attributes): void
    {
        $this->assertDatabaseHas('orders', $this->onlyExistingOrderColumns($attributes));
    }

    protected function assertOrderCarrierRate(int $orderId, float $expected): void
    {
        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $this->assertDatabaseHas('orders', [
                'id' => $orderId,
                'carrier_rate' => $expected,
            ]);

            return;
        }

        $costs = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($costs);
        $decoded = json_decode($costs, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(round($expected, 2), round((float) ($decoded[0]['amount'] ?? 0), 2));
    }

    protected function assertContractorsCostsContainPaymentForm(int $orderId, string $paymentForm): void
    {
        $costs = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($costs);
        $decoded = json_decode($costs, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($paymentForm, $decoded[0]['payment_form'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createManagementBankAccount(array $attributes = []): ManagementBankAccount
    {
        return ManagementBankAccount::query()->create(array_merge([
            'bank_name' => 'Сбер',
            'account_number' => '40702810123456789012',
            'account_mask' => '••••9012',
            'currency' => 'RUB',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createManagementStatementLine(array $attributes = []): ManagementStatementLine
    {
        if (! array_key_exists('bank_account_id', $attributes)) {
            $attributes['bank_account_id'] = $this->createManagementBankAccount()->id;
        }

        return ManagementStatementLine::query()->create(array_merge([
            'line_hash' => hash('sha256', uniqid('mgmt-line-', true)),
            'operation_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => 1000,
            'description' => 'Test line',
            'status' => 'pending',
            'source' => 'manual',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createManagementExpenseCategory(array $attributes = []): ManagementExpenseCategory
    {
        return ManagementExpenseCategory::query()->create(array_merge([
            'code' => 'test_cat_'.uniqid(),
            'name' => 'Test category',
            'kind' => 'overhead',
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $orderAttributes
     * @param  array<string, mixed>  $paymentTermsConfig
     * @param  array<string, mixed>  $financialTermAttributes
     */
    protected function createOrderWithPaymentTerms(
        array $orderAttributes,
        array $paymentTermsConfig,
        array $financialTermAttributes = [],
    ): Order {
        $paymentTermsJson = json_encode($paymentTermsConfig, JSON_THROW_ON_ERROR);

        if (Schema::hasColumn('orders', 'payment_terms')) {
            $orderAttributes['payment_terms'] = $paymentTermsJson;
        }

        $order = Order::factory()->create(
            $this->onlyExistingOrderColumns($orderAttributes),
        );

        FinancialTerm::factory()->create(array_merge([
            'order_id' => $order->id,
            'client_price' => (float) ($orderAttributes['customer_rate'] ?? 0),
            'payment_terms_snapshot' => $paymentTermsJson,
        ], $financialTermAttributes));

        return $order;
    }

    protected function expectedOrderExpense(float $carrierRate, float $additionalExpenses): float
    {
        $expense = Schema::hasColumn('orders', 'additional_expenses') ? $additionalExpenses : 0.0;

        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $expense += $carrierRate;
        }

        return $expense;
    }
}
