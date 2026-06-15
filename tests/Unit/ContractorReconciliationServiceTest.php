<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\Finance\ContractorReconciliationService;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorReconciliationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'orders',
            'contractors',
        ]);

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('type')->nullable();
            $table->string('inn')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('order_number')->nullable();
            $table->date('order_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function test_build_accepts_contractor_without_type_and_without_period(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Тест',
            'type' => null,
        ]);

        $service = new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService);

        $report = $service->build(
            $contractor->id,
            null,
            null,
            null,
            'admin',
            'all',
        );

        $this->assertSame('both', $report['contractor']['type']);
        $this->assertTrue($report['show_as_customer']);
        $this->assertTrue($report['show_as_carrier']);
        $this->assertSame('Услуги для контрагента (он — заказчик)', $report['as_customer']['title']);
        $this->assertSame('Услуги от контрагента (он — перевозчик)', $report['as_carrier']['title']);
    }

    public function test_build_uses_contractor_party_label_for_subcontractor_type(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Подряд',
            'type' => 'contractor',
        ]);

        $service = new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService);

        $report = $service->build(
            $contractor->id,
            null,
            null,
            null,
            'admin',
            'all',
        );

        $this->assertSame('contractor', $report['contractor']['type']);
        $this->assertSame('Услуги от контрагента (он — подрядчик)', $report['as_carrier']['title']);
    }

    public function test_build_includes_tranche_payments_for_customer_orders(): void
    {
        $this->schemaDropMany([
            'payment_schedule_payment_events',
            'payment_schedules',
        ]);

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('party', 16)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->nullable();
            $table->decimal('remaining_amount', 14, 2)->nullable();
            $table->date('planned_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('invoice_number', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->string('party', 16)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        $contractor = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $orderId = (int) DB::table('orders')->insertGetId([
            'customer_id' => $contractor->id,
            'order_number' => 'АС-2606-0001',
            'order_date' => '2026-06-01',
            'customer_rate' => 150000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = (int) DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 150000,
            'planned_date' => '2026-06-10',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedule_payment_events')->insert([
            'payment_schedule_id' => $scheduleId,
            'order_id' => $orderId,
            'contractor_id' => $contractor->id,
            'party' => 'customer',
            'amount' => 50000,
            'payment_date' => '2026-06-11',
            'transaction_reference' => 'ПП-100',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ContractorReconciliationService(new PaymentSchedulePaymentLedgerService);

        $report = $service->build(
            $contractor->id,
            null,
            null,
            null,
            'admin',
            'all',
        );

        $row = $report['as_customer']['rows'][0];
        $this->assertCount(1, $row['tranches']);
        $this->assertSame($scheduleId, $row['tranches'][0]['id']);
        $this->assertSame(50000.0, $row['tranches'][0]['paid']);
        $this->assertCount(1, $row['tranches'][0]['payments']);
        $this->assertSame('2026-06-11', $row['tranches'][0]['payments'][0]['date']);
    }
}
