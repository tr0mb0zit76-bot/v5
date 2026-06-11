<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Services\ManagementAccounting\ManagementAccountingMatchingService;
use App\Services\ManagementAccounting\ManagementReconcileRuleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingMatchingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_reconcile_rules',
            'management_statement_lines',
            'payment_schedules',
            'orders',
            'contractors',
            'management_expense_categories',
            'users',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('full_name')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->decimal('salary_accrued', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('party', 16)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2)->nullable();
            $table->date('planned_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->text('description');
            $table->timestamps();
        });

        ManagementExpenseCategory::query()->create([
            'code' => 'operational_customer_in',
            'name' => 'Оплата от заказчика',
            'kind' => 'operational_in',
            'is_active' => true,
        ]);

        ManagementExpenseCategory::query()->create([
            'code' => 'operational_carrier_out',
            'name' => 'Оплата перевозчику',
            'kind' => 'operational_out',
            'is_active' => true,
        ]);
    }

    public function test_suggests_operational_match_by_contractor_name_and_exact_amount_for_incoming_payment(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
            'full_name' => 'Общество с ограниченной ответственностью Ромашка',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0007',
            'customer_id' => $customer->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'amount' => 120000,
            'remaining_amount' => 120000,
            'planned_date' => '2026-06-10',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 120000,
            'description' => 'Поступление от ООО Ромашка за перевозку груза',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
        $this->assertGreaterThanOrEqual(80, $suggestion['match_confidence']);
        $this->assertStringContainsString('Ромашка', (string) $suggestion['match_notes']);
    }

    public function test_suggests_operational_match_for_outgoing_carrier_payment(): void
    {
        $carrier = Contractor::query()->create([
            'name' => 'ИП Волков',
            'full_name' => 'Индивидуальный предприниматель Волков Петр Сергеевич',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0011',
            'carrier_id' => $carrier->id,
        ]);

        $schedule = PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'amount' => 85000,
            'remaining_amount' => 85000,
            'planned_date' => '2026-06-12',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-11',
            'direction' => 'out',
            'amount' => 85000,
            'description' => 'Оплата по договору перевозки ИП Волков',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertSame($order->id, $suggestion['suggested_order_id']);
        $this->assertSame($schedule->id, $suggestion['suggested_payment_schedule_id']);
    }

    public function test_does_not_match_when_amount_differs(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-2506-0008',
            'customer_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $order->id,
            'party' => 'customer',
            'amount' => 120000,
            'remaining_amount' => 120000,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 50000,
            'description' => 'Поступление от ООО Ромашка',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertNotSame('operational', $suggestion['match_type']);
        $this->assertNull($suggestion['suggested_order_id']);
    }

    public function test_multiple_contractor_matches_return_candidates_without_auto_selection(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
        ]);

        $firstOrder = Order::query()->create([
            'order_number' => 'АС-2506-0101',
            'customer_id' => $customer->id,
        ]);

        $secondOrder = Order::query()->create([
            'order_number' => 'АС-2506-0102',
            'customer_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $firstOrder->id,
            'party' => 'customer',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'planned_date' => '2026-06-20',
            'status' => 'pending',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $secondOrder->id,
            'party' => 'customer',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'planned_date' => '2026-06-05',
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'Оплата от ООО Ромашка без номера заявки',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame('operational', $suggestion['match_type']);
        $this->assertNull($suggestion['suggested_order_id']);
        $this->assertNull($suggestion['suggested_payment_schedule_id']);
        $this->assertCount(2, $suggestion['suggested_candidates']);
        $this->assertStringContainsString('Несколько заявок', (string) $suggestion['match_notes']);
    }

    public function test_order_number_match_takes_priority_over_contractor_name(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО Ромашка',
        ]);

        $orderByNumber = Order::query()->create([
            'order_number' => 'АС-2506-0099',
            'customer_id' => $customer->id,
        ]);

        $orderByNameOnly = Order::query()->create([
            'order_number' => 'АС-2506-0001',
            'customer_id' => $customer->id,
        ]);

        $scheduleByNumber = PaymentSchedule::query()->create([
            'order_id' => $orderByNumber->id,
            'party' => 'customer',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $orderByNameOnly->id,
            'party' => 'customer',
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        $line = ManagementStatementLine::query()->make([
            'operation_date' => '2026-06-09',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'Оплата по заявке АС-2506-0099 от ООО Ромашка',
        ]);

        $suggestion = $this->matchingService()->suggestForLine($line);

        $this->assertSame($orderByNumber->id, $suggestion['suggested_order_id']);
        $this->assertSame($scheduleByNumber->id, $suggestion['suggested_payment_schedule_id']);
    }

    private function matchingService(): ManagementAccountingMatchingService
    {
        return new ManagementAccountingMatchingService(new ManagementReconcileRuleService);
    }
}
