<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\Finance\ContractorReconciliationService;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use Illuminate\Database\Schema\Blueprint;
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
}
