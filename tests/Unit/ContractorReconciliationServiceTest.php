<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\Finance\ContractorReconciliationService;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractorReconciliationServiceTest extends TestCase
{
    public function test_build_accepts_contractor_without_type_and_without_period(): void
    {
        $contractor = Contractor::query()->create([
            'name' => 'ООО Тест',
            'type' => 'both',
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
        $contractor = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $orderId = $this->insertOrderRow([
            'customer_id' => $contractor->id,
            'order_number' => 'АС-2606-0001',
            'order_date' => '2026-06-01',
            'customer_rate' => 150000,
        ]);

        $scheduleId = (int) DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
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
